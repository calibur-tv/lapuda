<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\VirtualCoinService;
use App\Models\Answer;
use App\Models\Image;
use App\Models\LightCoinRecord;
use App\Models\Post;
use App\Models\Score;
use App\Models\User;
use App\Models\UserSign;
use App\Models\VirtualCoin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MigrationCoin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'MigrationCoin';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'migration coin';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->migration_step_1();
        $this->migration_step_2();
        $this->migration_step_3();
        $this->migration_step_4();
        $this->migration_step_5();
        $this->migration_step_6();
        $this->migration_step_7();
        $this->migration_step_8();
        $this->migration_step_9();
        $this->migration_step_10();
        $this->migration_step_11();
        $this->migration_step_12();
        $this->migration_step_16();
        return true;
    }

    // 同步签到
    protected function migration_step_1()
    {
        $sign = UserSign
            ::where('migration_state', 0)
            ->orderBy('id', 'ASC')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($sign))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($sign as $item)
        {
            $state = UserSign
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            UserSign
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->daySign($item['user_id']);

            UserSign
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步邀请
    protected function migration_step_2()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 1)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->inviteUser($item['to_user_id'], $item['from_user_id'], $item['order_amount']);

            LightCoinRecord
                ::where('order_id', $item['order_id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步被邀请
    protected function migration_step_3()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 17)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->invitedNewbieCoinGift($item['from_user_id'], $item['to_user_id'], $item['order_amount']);

            LightCoinRecord
                ::where('order_id', $item['order_id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步普通用户活跃送团子
    protected function migration_step_4()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 2)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->userActivityReward($item['to_user_id']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步版主活跃送光玉
    protected function migration_step_5()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->whereIn('to_product_type', [3, 16])
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->masterActiveReward($item['to_user_id'], $item['to_product_type'] == 16);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步赠送团子给用户
    protected function migration_step_6()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 13)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->coinGift($item['to_user_id'], $item['order_amount']);

            LightCoinRecord
                ::where('order_id', $item['order_id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步赠送光玉给用户
    protected function migration_step_7()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 14)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->lightGift($item['to_user_id'], $item['order_amount']);

            LightCoinRecord
                ::where('order_id', $item['order_id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步打赏内容
    protected function migration_step_8()
    {
        $table = 'post_reward';
        $list = DB
            ::table($table)
            ->where('migration_state', 0)
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($list))
        {
            $table = 'video_reward';
            $list = DB
                ::table($table)
                ->where('migration_state', 0)
                ->take(2000)
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            $table = 'score_reward';
            $list = DB
                ::table($table)
                ->where('migration_state', 0)
                ->take(2000)
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            $table = 'image_reward';
            $list = DB
                ::table($table)
                ->where('migration_state', 0)
                ->take(2000)
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            $table = 'answer_reward';
            $list = DB
                ::table($table)
                ->where('migration_state', 0)
                ->take(2000)
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($list as $item)
        {
            $state = DB
                ::table($table)
                ->where('id', $item->id)
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            DB
                ::table($table)
                ->where('id', $item->id)
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item->created_at);
            $modelType = explode('_', $table)[0];
            $repository = $this->getRepositoryByType($modelType);
            $model = $repository->item($item->modal_id, true);
            $coinService->rewardUserContent($modelType, $item->user_id, $model['user_id'], $item->modal_id);

            DB
                ::table($table)
                ->where('id', $item->id)
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步原创内容被删除
    protected function migration_step_9()
    {
        $table = 'post_reward';
        $list = Post
            ::where('is_creator', 1)
            ->onlyTrashed()
            ->whereIn('id', function ($query) use ($table)
            {
                $query
                    ->from($table)
                    ->select('modal_id')
                    ->where('migration_state', 2)
                    ->groupBy('modal_id');
            })
            ->select('id', 'user_id', 'deleted_at')
            ->get()
            ->toArray();

        if (empty($list))
        {
            $table = 'score_reward';
            $list = Score
                ::where('is_creator', 1)
                ->onlyTrashed()
                ->whereIn('id', function ($query) use ($table)
                {
                    $query
                        ->from($table)
                        ->select('modal_id')
                        ->where('migration_state', 2)
                        ->groupBy('modal_id');
                })
                ->select('id', 'user_id', 'deleted_at')
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            $table = 'image_reward';
            $list = Image
                ::where('is_creator', 1)
                ->onlyTrashed()
                ->whereIn('id', function ($query) use ($table)
                {
                    $query
                        ->from($table)
                        ->select('modal_id')
                        ->where('migration_state', 2)
                        ->groupBy('modal_id');
                })
                ->select('id', 'user_id', 'deleted_at')
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            $table = 'answer_reward';
            $list = Answer
                ::where('source_url', '<>', '')
                ->onlyTrashed()
                ->whereIn('id', function ($query) use ($table)
                {
                    $query
                        ->from($table)
                        ->select('modal_id')
                        ->where('migration_state', 2)
                        ->groupBy('modal_id');
                })
                ->select('id', 'user_id', 'deleted_at')
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($list as $item)
        {
            $coinService->setTime($item['deleted_at']);
            $modelType = explode('_', $table)[0];
            $amount = DB::table($table)->where('modal_id', $item['id'])->count();
            $coinService->deleteUserContent($modelType, $item['user_id'], $item['id'], $amount);
            DB
                ::table($table)
                ->where('modal_id', $item['id'])
                ->update([
                    'migration_state' => 3
                ]);
        }

        return false;
    }

    // 同步应援偶像
    protected function migration_step_10()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 9)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->cheerForIdol($item['from_user_id'], $item['to_product_id']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步提现
    protected function migration_step_11()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 10)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->withdraw($item['from_user_id'], $item['order_amount'] < 100 ? 100 : $item['order_amount']);

            LightCoinRecord
                ::where('order_id', $item['order_id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // 同步承包视频
    protected function migration_step_12()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 18)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(2000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return true;
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $state = LightCoinRecord
                ::where('id', $item['id'])
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);

            $coinService->setTime($item['created_at']);
            $coinService->buyVideoPackage($item['from_user_id'], $item['to_product_id'], 10);

            LightCoinRecord
                ::where('order_id', $item['order_id'])
                ->update([
                    'migration_state' => 2
                ]);
        }

        return false;
    }

    // TODO：同步撤销应援
    protected function migration_step_13()
    {
        return true;
    }

    // 删除重复数据
    protected function migration_step_14()
    {
        $ids = VirtualCoin
            ::select(DB::raw('MIN(id) AS id'))
            ->where('channel_type', '<>', 9)
            ->take(1000)
            ->groupBy(['user_id', 'created_at', 'channel_type', 'about_user_id', 'product_id'])
            ->havingRaw('COUNT(id) > 1')
            ->pluck('id')
            ->toArray();

        VirtualCoin::whereIn('id', $ids)->delete();
    }

    // TODO：偶像应援对账
    protected function migration_step_15()
    {

    }

    // 修改 user 信息
    protected function migration_step_16()
    {
        $ids = User
            ::where('migration_state', 0)
            ->withTrashed()
            ->take(2000)
            ->pluck('id')
            ->toArray();

        if (empty($ids))
        {
            return true;
        }

        foreach ($ids as $userId)
        {
            $state = User
                ::where('id', $userId)
                ->withTrashed()
                ->pluck('migration_state')
                ->first();

            if ($state != 0)
            {
                continue;
            }

            User
                ::where('id', $userId)
                ->withTrashed()
                ->update([
                    'migration_state' => 1
                ]);

            $coinCount = VirtualCoin
                ::where('user_id', $userId)
                ->whereIn('channel_type', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 16, 20, 21])
                ->sum('amount');

            $moneyCount = VirtualCoin
                ::where('user_id', $userId)
                ->whereIn('channel_type', [10, 11, 12, 13, 14, 15, 17, 18, 19, 23])
                ->sum('amount');

            $state = 2;
            if ($coinCount + $moneyCount < 0)
            {
                $state = 3;
            }
            else
            {
                if ($coinCount < 0)
                {
                    $coinCount = 0;
                    $moneyCount = $moneyCount + $coinCount;
                }
                if ($moneyCount < 0)
                {
                    $moneyCount = 0;
                    $coinCount = $coinCount + $moneyCount;
                }
            }

            User
                ::where('id', $userId)
                ->withTrashed()
                ->update([
                    'migration_state' => $state,
                    'virtual_coin' => $coinCount,
                    'money_coin' => $moneyCount
                ]);

            // Redis::DEL("user_{$userId}");
        }

        return false;
    }

    protected function getRepositoryByType($type)
    {
        switch ($type)
        {
            case 'post':
                return new PostRepository();
                break;
            case 'image':
                return new ImageRepository();
                break;
            case 'score':
                return new ScoreRepository();
                break;
            case 'answer':
                return new AnswerRepository();
                break;
            case 'video':
                return new VideoRepository();
                break;
            default:
                return null;
        }
    }
}