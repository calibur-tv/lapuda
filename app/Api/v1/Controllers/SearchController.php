<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Services\LightCoinService;
use App\Models\LightCoin;
use App\Models\LightCoinRecord;
use App\Models\User;
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

    public function test()
    {
        $userIds = User::where('migration_state', 1)
            ->pluck('id')
            ->toArray();

        foreach ($userIds as $userId)
        {
            $inviteRecordCount = LightCoinRecord
                ::where('to_product_type', 1)
                ->where('to_user_id', $userId)
                ->count();
            if (!$inviteRecordCount)
            {
                User::where('id', $userId)
                    ->update([
                        'migration_state' => 2
                    ]);
                continue;
            }
            $recordList = LightCoinRecord
                ::where('to_product_type', 1)
                ->where('to_user_id', $userId)
                ->select(DB::raw('count(*) as count, from_user_id'))
                ->groupBy('from_user_id')
                ->get()
                ->toArray();
            $needDelete = false;

            foreach ($recordList as $record)
            {
                if ($record['count'] > 1)
                {
                    $needDelete = true;
                }
            }
            if (!$needDelete)
            {
                User::where('id', $userId)
                    ->update([
                        'migration_state' => 2
                    ]);
                continue;
            }
            foreach ($recordList as $record)
            {
                if ($record['count'] > 1)
                {
                    $fromUserId = $record['from_user_id'];
                    $needDeleteRecord = LightCoinRecord
                        ::where('to_product_type', 1)
                        ->where('to_user_id', $userId)
                        ->where('from_user_id', $fromUserId)
                        ->get()
                        ->toArray();

                    $needDeleteCount = count($needDeleteRecord) - 1;
                    $hasDeleteCount = 0;
                    foreach ($needDeleteRecord as $delRecord)
                    {
                        if ($hasDeleteCount === $needDeleteCount)
                        {
                            break;
                        }
                        $exRecordCount = LightCoinRecord
                            ::where('coin_id', $delRecord['coin_id'])
                            ->count();
                        if ($exRecordCount == 1)
                        {
                            LightCoin::where('id', $delRecord['coin_id'])->delete();
                            LightCoinRecord::where('id', $delRecord['id'])->delete();
                            $hasDeleteCount++;
                        }
                    }
                }
            }

            $light_count = LightCoin
                ::where('holder_type', 1)
                ->where('holder_id', $userId)
                ->where('state', 1)
                ->count();

            $coin_count = LightCoin
                ::where('holder_type', 1)
                ->where('holder_id', $userId)
                ->where('state', 0)
                ->count();

            User::where('id', $userId)
                ->update([
                    'light_count' => $light_count,
                    'coin_count' => $coin_count,
                    'migration_state' => 2
                ]);

            Redis::DEL('user', $userId);
            Redis::DEL("user_{$userId}_coin_records");
        }

        return $this->resOK('');
    }
}
