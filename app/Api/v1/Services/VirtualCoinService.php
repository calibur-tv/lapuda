<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019/2/7
 * Time: 上午11:56
 */

namespace App\Api\V1\Services;


use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Models\User;
use App\Models\VirtualCoin;
use Illuminate\Support\Facades\Redis;

class VirtualCoinService
{
    // 拥有光玉+团子个数
    public function hasMoneyCount($currentUser)
    {
        return $currentUser->money_coin + $currentUser->virtual_coin;
    }

    // 拥有光玉个数
    public function hasLightCount($currentUser)
    {
        return $currentUser->money_coin;
    }

    // 拥有团子个数
    public function hasCoinCount($currentUser)
    {
        return $currentUser->virtual_coin;
    }

    // 用户的交易记录
    public function getUserRecord($userId, $page = 0, $count = 15)
    {
        $repository = new Repository();
        $ids = $repository->RedisList("user_{$userId}_virtual_coin_records", function () use ($userId)
        {
            return VirtualCoin
                ::where('user_id', $userId)
                ->orderBy('created_at', 'DESC')
                ->pluck('id')
                ->toArray();
        });
        $idsObj = $repository->filterIdsByPage($ids, $page, $count);
        $records = VirtualCoin
            ::whereIn('id', $idsObj['ids'])
            ->get()
            ->toArray();

        $result = [];
        foreach ($records as $item)
        {
            $type = intval($item['channel_type']);
            $product_id = intval($item['product_id']);
            $amount = $item['amount'];
            $model = null;
            $user = null;
            if ($item['about_user_id'])
            {
                $userRepository = new UserRepository();
                $aboutUser = $userRepository->item($item['about_user_id']);
                $user = [
                    'nickname' => $aboutUser['nickname'],
                    'zone' => $aboutUser['zone']
                ];
            }
            if ($product_id)
            {
                $repository = $this->getProductRepositoryByType($type);
                if ($repository)
                {
                    $item = $repository->item($product_id, true);
                    $model = [
                        'id' => $product_id,
                        'title' => isset($item['title']) ? $item['title'] : $item['name']
                    ];
                }
            }
            $result[] = [
                'type' => $type,
                'user' => $user,
                'model' => $model,
                'amount' => floatval($amount),
                'created_at' => $item['created_at']
            ];
        }

        return [
            'list' => $result,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ];
    }

    // 用户的收入支出成交额
    public function getUserBalance($userId)
    {
        $get = VirtualCoin
            ::where('user_id', $userId)
            ->where('amount', '>', 0)
            ->sum('amount');

        $set = VirtualCoin
            ::where('user_id', $userId)
            ->where('amount', '<', 0)
            ->sum('amount');

        return [
            'get' => floatval($get),
            'set' => -floatval($set)
        ];
    }

    // 每日签到送团子
    public function daySign($userId, $amount = 1)
    {
        $this->addCoin($userId, $amount, 0, 0, 0);
    }

    // 邀请用户注册赠送团子
    public function inviteUser($oldUserId, $newUserId, $amount = 5)
    {
        $this->addCoin($oldUserId, $amount, 1, 0, $newUserId);
    }

    // 用户活跃送团子
    public function userActivityReward($userId)
    {
        $this->addCoin($userId, 1, 2, 0, 0);
        // $this->addMoney($userId, 1, 18, 0, 0);
    }

    // 版主活跃送光玉
    public function masterActiveReward($userId)
    {
        $this->addMoney($userId, 1, 19, 0, 0);
    }

    // 给用户赠送团子
    public function coinGift($toUserId, $amount)
    {
        $this->addCoin($toUserId, $amount, 16, 0, 0);
    }

    // 给用户赠送光玉
    public function lightGift($toUserId, $amount)
    {
        $this->addMoney($toUserId, $amount, 17, 0, 0);
    }

    // 被邀请注册用户送团子
    public function invitedNewbieCoinGift($oldUserId, $newUserId, $amount = 2)
    {
        $this->addCoin($newUserId, $amount, 20, 0, $oldUserId);
    }

    // 承包视频
    public function buyVideoPackage($fromUserId, $productId, $amount, $toUserId = 2)
    {
        $result = $this->useCoinFirst($fromUserId, $amount, 21, $productId, $toUserId);
        if (!$result)
        {
            return false;
        }
        $this->addMoney($toUserId, $amount, 23, $productId, $fromUserId);
        return true;
    }

    // 给别人打赏
    public function rewardUserContent($model, $fromUserId, $toUserId, $productId, $amount = 1)
    {
        switch ($model)
        {
            case 'post':
                $channelType = 4;
                break;
            case 'image':
                $channelType = 5;
                break;
            case 'score':
                $channelType = 6;
                break;
            case 'answer':
                $channelType = 7;
                break;
            case 'video':
                $channelType = 8;
                break;
            default:
                $channelType = 0;
        }
        if (!$channelType)
        {
            return false;
        }
        $result = $this->useCoinFirst($fromUserId, $amount, $channelType, $productId, $toUserId);
        if (!$result)
        {
            return false;
        }
        $this->addMoney($toUserId, $amount, $channelType, $productId, $fromUserId);

        return true;
    }

    // 移除某个内容的打赏
    public function deleteUserContent($model, $authorId, $productId, $amount)
    {
        switch ($model)
        {
            case 'post':
                $channelType = 15;
                break;
            case 'image':
                $channelType = 14;
                break;
            case 'score':
                $channelType = 13;
                break;
            case 'answer':
                $channelType = 12;
                break;
            case 'video':
                $channelType = 11;
                break;
            default:
                $channelType = 0;
        }
        if (!$channelType)
        {
            return false;
        }

        return $this->useMoneyFirst($authorId, $amount, $channelType, $productId, 0);
    }

    // TODO
    public function rollbackContentCoin()
    {

    }

    // 为偶像应援
    public function cheerForIdol($userId, $idolId, $amount = 1)
    {
        return $this->useCoinFirst($userId, $amount, 9, $idolId, 0);
    }

    // 提现
    public function withdraw($userId, $amount)
    {
        return $this->useMoneyFirst($userId, $amount, 10, 0, 0);
    }

    // 撤销用户的所有应援
    public function undoUserCheer($userId)
    {
        // 因为不扣钱，也不给钱，所以 do nothing
    }

    private function useCoinFirst($userId, $amount, $channel_type, $product_id, $about_user_id)
    {
        if ($amount > 0)
        {
            $amount = -$amount;
        }

        $balance = User
            ::where('id', $userId)
            ->withTrashed()
            ->select('virtual_coin', 'money_coin')
            ->first()
            ->toArray();

        if ($balance['virtual_coin'] + $balance['money_coin'] + $amount < 0)
        {
            return false;
        }

        VirtualCoin::create([
            'user_id' => $userId,
            'amount' => $amount,
            'channel_type' => $channel_type,
            'product_id' => $product_id,
            'about_user_id' => $about_user_id
        ]);

        if ($balance['virtual_coin'] + $amount < 0)
        {
            User
                ::where('id', $userId)
                ->withTrashed()
                ->increment('virtual_coin', -$balance['virtual_coin']);

            User
                ::where('id', $userId)
                ->withTrashed()
                ->increment('money_coin', $balance['virtual_coin'] + $amount);

            if (Redis::EXISTS("user_{$userId}"))
            {
                Redis::HINCRBYFLOAT("user_{$userId}", 'virtual_coin', -$balance['virtual_coin']);
                Redis::HINCRBYFLOAT("user_{$userId}", 'money_coin', $balance['virtual_coin'] + $amount);
            }
        }
        else
        {
            User
                ::where('id', $userId)
                ->withTrashed()
                ->increment('virtual_coin', $amount);

            if (Redis::EXISTS("user_{$userId}"))
            {
                Redis::HINCRBYFLOAT("user_{$userId}", 'virtual_coin', $amount);
            }
        }

        return true;
    }

    private function useMoneyFirst($userId, $amount, $channel_type, $product_id, $about_user_id)
    {
        if ($amount > 0)
        {
            $amount = -$amount;
        }

        $balance = User
            ::where('id', $userId)
            ->withTrashed()
            ->select('virtual_coin', 'money_coin')
            ->first()
            ->toArray();

        if ($balance['virtual_coin'] + $balance['money_coin'] + $amount < 0)
        {
            return false;
        }

        VirtualCoin::create([
            'user_id' => $userId,
            'amount' => $amount,
            'channel_type' => $channel_type,
            'product_id' => $product_id,
            'about_user_id' => $about_user_id
        ]);

        if ($balance['money_coin'] + $amount < 0)
        {
            User
                ::where('id', $userId)
                ->withTrashed()
                ->increment('money_coin', -$balance['money_coin']);

            User
                ::where('id', $userId)
                ->increment('virtual_coin', $balance['money_coin'] + $amount);

            if (Redis::EXISTS("user_{$userId}"))
            {
                Redis::HINCRBYFLOAT("user_{$userId}", 'money_coin', -$balance['money_coin']);
                Redis::HINCRBYFLOAT("user_{$userId}", 'virtual_coin', $balance['money_coin'] + $amount);
            }
        }
        else
        {
            User
                ::where('id', $userId)
                ->withTrashed()
                ->increment('money_coin', $amount);

            if (Redis::EXISTS("user_{$userId}"))
            {
                Redis::HINCRBYFLOAT("user_{$userId}", 'money_coin', $amount);
            }
        }

        return true;
    }

    private function addCoin($userId, $amount, $channel_type, $product_id, $about_user_id)
    {
        if ($amount < 0)
        {
            $amount = +$amount;
        }

        VirtualCoin::create([
            'user_id' => $userId,
            'amount' => $amount,
            'channel_type' => $channel_type,
            'product_id' => $product_id,
            'about_user_id' => $about_user_id
        ]);

        User
            ::where('id', $userId)
            ->withTrashed()
            ->increment('virtual_coin', $amount);

        if (Redis::EXISTS("user_{$userId}"))
        {
            Redis::HINCRBYFLOAT("user_{$userId}", 'virtual_coin', $amount);
        }
    }

    private function addMoney($userId, $amount, $channel_type, $product_id, $about_user_id)
    {
        if ($amount < 0)
        {
            $amount = +$amount;
        }

        VirtualCoin::create([
            'user_id' => $userId,
            'amount' => $amount,
            'channel_type' => $channel_type,
            'product_id' => $product_id,
            'about_user_id' => $about_user_id
        ]);

        User
            ::where('id', $userId)
            ->withTrashed()
            ->increment('money_coin', $amount);

        if (Redis::EXISTS("user_{$userId}"))
        {
            Redis::HINCRBYFLOAT("user_{$userId}", 'money_coin', $amount);
        }
    }

    private function getProductRepositoryByType($type)
    {
        switch ($type)
        {
            case 0:
                return null;
                break;
            case 1:
                return new UserRepository();
                break;
            case 2:
                return null;
                break;
            case 3:
                return null;
                break;
            case 4:
                return new PostRepository();
                break;
            case 5:
                return new ImageRepository();
                break;
            case 6:
                return new ScoreRepository();
                break;
            case 7:
                return new QuestionRepository();
                break;
            case 8:
                return new VideoRepository();
                break;
            case 9:
                return new CartoonRoleRepository();
                break;
            case 10:
                return null;
                break;
            case 11:
                return new VideoRepository();
                break;
            case 12:
                return new QuestionRepository();
                break;
            case 13:
                return new ScoreRepository();
                break;
            case 14:
                return new ImageRepository();
                break;
            case 15:
                return new PostRepository();
                break;
            case 16:
                return null;
                break;
            case 17:
                return null;
                break;
            case 18:
                return null;
                break;
            case 19:
                return null;
                break;
            case 20:
                return null;
                break;
            case 21:
                return new VideoRepository();
                break;
            case 22:
                return null;
                break;
            case 23:
                return new VideoRepository();
                break;
            default:
                return null;
        }
    }
}