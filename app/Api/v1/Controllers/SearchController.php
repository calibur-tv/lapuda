<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Services\LightCoinService;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use App\Models\LightCoin;
use App\Models\LightCoinRecord;
use App\Models\User;
use App\Models\UserSign;
use Illuminate\Http\Request;
use App\Services\OpenSearch\Search;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("搜索相关接口")
 */
class SearchController extends Controller
{
    /**
     * 搜索接口
     *
     * > 目前支持的参数格式：
     * type：all, user, bangumi, video，post，role，image，score，question，answer
     * 返回的数据与 flow/list 返回的相同
     *
     * @Get("/search/new")
     *
     * @Parameters({
     *      @Parameter("type", description="要检测的类型", type="string", required=true),
     *      @Parameter("q", description="搜索的关键词", type="string", required=true),
     *      @Parameter("page", description="搜索的页码", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body="数据列表")
     * })
     */
    public function search(Request $request)
    {
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = $request->get('type') ?: 'all';
        $page = intval($request->get('page')) ?: 0;

        $search = new Search();
        $result = $search->retrieve(strtolower($key), $type, $page);

        return $this->resOK($result);
    }

    /**
     * 获取所有番剧列表
     *
     * > 返回所有的番剧列表，用户搜索提示，可以有效减少请求数
     *
     * @Get("/search/bangumis")
     *
     * @Transaction({
     *      @Response(200, body="番剧列表")
     * })
     */
    public function bangumis()
    {
        $bangumiRepository = new BangumiRepository();

        return $this->resOK($bangumiRepository->searchAll());
    }

    // 删除重复的记录，和与重复记录相关的金币
    public function migration_1()
    {
        $deleteRecordIds = $this->getDeleteRecordId();
        if (empty($deleteRecordIds))
        {
            return $this->resOK('deleted all');
        }

        foreach ($deleteRecordIds as $recordId)
        {
            $record = LightCoinRecord
                ::where('id', $recordId)
                ->first()
                ->toArray();

            $coinId = $record['coin_id'];
            $records = LightCoinRecord
                        ::where('coin_id', $coinId)
                        ->get()
                        ->toArray();
            $currentIds = [];
            foreach ($records as $recordItem)
            {
                $currentIds[] = $recordItem['id'];
                if ($recordItem['to_user_id'])
                {
                    User
                        ::where('id', $recordItem['to_user_id'])
                        ->withTrashed()
                        ->increment('coin_gift');
                }
                if ($recordItem['from_user_id'])
                {
                    User
                        ::where('id', $recordItem['from_user_id'])
                        ->withTrashed()
                        ->increment('coin_gift');
                }
            }
            LightCoinRecord::whereIn('id', $currentIds)->delete();
            LightCoin::where('id', $coinId)->delete();
        }

        return $this->resOK('need redo');
    }

    // 对账，如果交易记录里面没有，那就认为他没做过，按用户遍历，再按 to_product_type 遍历
    public function migration_2()
    {
        $userIds = User
            ::where('migration_state', 1)
            ->withTrashed()
            ->take(10000)
            ->pluck('id')
            ->toArray();

        if (empty($userIds))
        {
            return $this->resOK('deleted all');
        }

        foreach ($userIds as $uid)
        {
            // 0 —— 签到
            $this->deleteSignIfNotExist($uid);
            // 1 —— 邀请注册，不需要
            // 2 —— 普通用户活跃送团子，不需要
            // 3 —— 吧主活跃送团子，不需要
            // 4 —— 打赏帖子
            $this->deleteRewardIfNotExits($uid, 4, 'post_reward');
            // 5 —— 打赏相册
            $this->deleteRewardIfNotExits($uid, 5, 'image_reward');
            // 6 —— 打赏漫评
            $this->deleteRewardIfNotExits($uid, 6, 'score_reward');
            // 7 —— 打赏回答
            $this->deleteRewardIfNotExits($uid, 7, 'answer_reward');
            // 8 —— 打赏视频
            $this->deleteRewardIfNotExits($uid, 8, 'video_reward');
            // 9 —— 应援偶像
            $this->computedUserCheerForIdol($uid);
            // 10 —— 提现 TODO，应该分出一个提现的记录表
            // 11 —— 发表内容被删除，是否需要更详细的记录 TODO
            // 12 —— 账号被封禁然后撤销其操作 TODO
            User
                ::where('id', $uid)
                ->withTrashed()
                ->update([
                    'migration_state' => 2
                ]);
        }

        return $this->resOK('need redo');
    }

    // 修改用户的计算值
    public function migration_3()
    {
        $userIds = User
            ::whereIn('id', [17, 138])
            ->pluck('id')
            ->toArray();

        if (empty($userIds))
        {
            return $this->resOK('migration all');
        }

        foreach ($userIds as $uid)
        {
            $light_count = LightCoin
                ::where('holder_type', 1)
                ->where('holder_id', $uid)
                ->where('state', 1)
                ->count();

            $coin_count = LightCoin
                ::where('holder_type', 1)
                ->where('holder_id', $uid)
                ->where('state', 0)
                ->count();

            User::where('id', $uid)
                ->withTrashed()
                ->update([
                    'light_count' => $light_count,
                    'coin_count_v2' => $coin_count
                ]);

            Redis::DEL('user', $uid);
        }

        return $this->resOK('need redo');
    }

    // 给用户补发团子
    public function migration_4()
    {
        $userIds = User
            ::where('migration_state', 4)
            ->where('coin_gift', '<>', 0)
            ->where('id', '>', 2)
            ->withTrashed()
            ->pluck('id')
            ->toArray();

        if (empty($userIds))
        {
            return $this->resOK('gift all');
        }

        $lightCoinService = new LightCoinService();
        foreach ($userIds as $uid)
        {
            $state = User
                ::where('id', $uid)
                ->withTrashed()
                ->pluck('migration_state')
                ->first();

            if ($state != 4)
            {
                continue;
            }

            User
                ::where('id', $uid)
                ->withTrashed()
                ->update([
                    'migration_state' => 5
                ]);

            $amount = User
                ::where('id', $uid)
                ->withTrashed()
                ->pluck('coin_gift')
                ->first();

            $lightCoinService->lightGift($uid, $amount);
            Redis::DEL("user_{$uid}_coin_records");

            User
                ::where('id', $uid)
                ->withTrashed()
                ->update([
                    'migration_state' => 6,
                    'coin_gift' => 0
                ]);
        }

        return $this->resOK('ok');
    }

    // migration提现
    public function migration_5()
    {
        $log = DB
            ::table('user_coin')
            ->where('type', 5)
            ->get()
            ->toArray();

        $lightCoinService = new LightCoinService();
        foreach ($log as $item)
        {
            $result = $lightCoinService->withdraw($item->user_id, $item->count, '', $item->created_at);
            if ($result)
            {
                DB::table('user_coin')->where('id', $item->id)->delete();
            }
        }

        return $this->resOK('success');
    }

    public function migration_6()
    {
        $lightCoinService = new LightCoinService();
        $lightCoinService->lightGift(61548, 5);
        $lightCoinService->coinGift(61569, 2);

        return $this->resOK('success');
    }

    protected function computedUserCheerForIdol($userId)
    {
        $records = LightCoinRecord
            ::where('from_user_id', $userId)
            ->where('to_product_type', 9)
            ->select(DB::raw('count(*) as star_count, to_product_id AS role_id'))
            ->groupBy('to_product_id')
            ->get()
            ->toArray();

        $recordRoleIds = [];
        foreach ($records as $recordItem)
        {
            $recordRoleIds[] = $recordItem['role_id'];

            $cheer = CartoonRoleFans
                ::where('user_id', $userId)
                ->where('role_id', $recordItem['role_id'])
                ->first();

            if (!$cheer)
            {
                // 根本没有这条记录，那就补上
                CartoonRole::create([
                    'role_id' => $recordItem['role_id'],
                    'user_id' => $userId,
                    'star_count' => $recordItem['star_count']
                ]);
                continue;
            }

            if ($cheer->star_count == $recordItem['star_count'])
            {
                continue;
            }

            CartoonRoleFans
                ::where('id', $cheer->id)
                ->update([
                    'star_count' => $recordItem['star_count']
                ]);
        }

        $cheerRoleIds = CartoonRoleFans
            ::where('user_id', $userId)
            ->pluck('role_id')
            ->toArray();

        // 将有应援记录，但是没有交易记录的数据删除
        $deletedRoleId = array_diff($cheerRoleIds, $recordRoleIds);
        CartoonRoleFans
            ::where('user_id', $userId)
            ->whereIn('role_id', $deletedRoleId)
            ->delete();
    }

    protected function deleteRewardIfNotExits($userId, $type, $rewardTable)
    {
        $records = DB
            ::table($rewardTable)
            ->where('user_id', $userId)
            ->get()
            ->toArray();

        foreach ($records as $recordItem)
        {
            $hasRecord = LightCoinRecord
                ::where('to_product_type', $type)
                ->where('to_product_id', $recordItem->modal_id)
                ->where('from_user_id', $userId)
                ->count();

            if (!$hasRecord)
            {
                DB
                    ::table($rewardTable)
                    ->where('id', $recordItem->id)
                    ->delete();
            }
        }
    }

    protected function deleteSignIfNotExist($userId)
    {
        $daySign = UserSign
            ::where('user_id', $userId)
            ->get()
            ->toArray();

        foreach ($daySign as $signItem)
        {
            $time = explode(' ', $signItem['created_at'])[0];
            $begin = "{$time} 00:00:00";
            $end = "{$time} 23:59:59";
            $hasItem = LightCoinRecord
                ::where('to_product_type', 0)
                ->where('to_user_id', $userId)
                ->where('created_at', '>=', $begin)
                ->where('created_at', '<', $end)
                ->count();

            if (!$hasItem)
            {
                UserSign
                    ::where('id', $signItem['id'])
                    ->delete();
            }
        }
    }

    protected function getDeleteRecordId()
    {
        return DB
            ::table('light_coin_records')
            ->select(DB::raw('MIN(id) AS id'))
            ->whereIn('to_product_type', [4, 5, 6, 7, 8])
            ->orderBy('id', 'DESC')
            ->groupBy(['from_user_id', 'to_user_id', 'to_product_id', 'to_product_type'])
            ->havingRaw('COUNT(id) > 1')
            ->pluck('id')
            ->toArray();
    }
}
