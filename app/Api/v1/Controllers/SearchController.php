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
        $coins = DB
            ::table('light_coins_v2')
            ->where('id', '>', 641036)
            ->where('migration_state', 0)
            ->get()
            ->toArray();

        if (!$coins)
        {
            return $this->resOK('new coin migration ok');
        }

        foreach ($coins as $item)
        {
            $records = DB
                ::table('light_coin_records_v2')
                ->where('coin_id', $item->id)
                ->get()
                ->toArray();

            $shouldNext = false;
            foreach ($records as $record)
            {
                if ($record->to_product_type == 1 && $record->id > 806224)
                {
                    $shouldNext = true;
                    break;
                }
            }
            if ($shouldNext)
            {
                DB
                    ::table('light_coins_v2')
                    ->where('id', $item->id)
                    ->update([
                        'migration_state' => 1
                    ]);
                continue;
            }
            foreach ($records as $record)
            {
                $count = DB
                    ::table('light_coin_records')
                    ->where('order_id', $record->order_id)
                    ->count();
                if ($count)
                {
                    $shouldNext = true;
                    break;
                }
            }
            if ($shouldNext)
            {
                DB
                    ::table('light_coins_v2')
                    ->where('id', $item->id)
                    ->update([
                        'migration_state' => 2
                    ]);
                continue;
            }

            $newId = DB
                ::table('light_coins')
                ->insertGetId([
                    'holder_id' => $item->holder_id,
                    'holder_type' => $item->holder_type,
                    'origin_from' => $item->origin_from,
                    'state' => $item->origin_from,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at
                ]);

            foreach ($records as $record)
            {
                DB
                    ::table('light_coin_records')
                    ->insert([
                        'coin_id' => $newId,
                        'order_id' => $record->order_id,
                        'from_user_id' => $record->from_user_id,
                        'to_user_id' => $record->to_user_id,
                        'to_product_id' => $record->to_product_id,
                        'to_product_type' => $record->to_product_type,
                        'order_amount' => $record->order_amount,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at
                    ]);
            }

            DB
                ::table('light_coins_v2')
                ->where('id', $item->id)
                ->update([
                    'migration_state' => 3
                ]);
        }

        return $this->resOK('success');
    }
}
