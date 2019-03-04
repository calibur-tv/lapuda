<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Api\V1\Repositories;

use App\Api\V1\Transformers\UserTransformer;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use App\Models\VirtualIdolDeal;
use App\Models\VirtualIdolDealRecord;
use App\Models\VirtualIdolOwner;
use App\Models\VirtualIdolPorduct;
use App\Models\VirtualIdolPriceDraft;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CartoonRoleRepository extends Repository
{
    public function item($id)
    {
        if (!$id)
        {
            return null;
        }

        return $this->RedisHash($this->idolItemCacheKey($id), function () use ($id)
        {
           $role = CartoonRole::find($id);

           if (is_null($role))
           {
               return null;
           }

           return $role->toArray();
        });
    }

    public function history($roleId)
    {
        return $this->Cache("cartoon_role_{$roleId}_history", function () use ($roleId)
        {
            $userRepository = new UserRepository();
            $userTransformer = new UserTransformer();

            $list = DB
                ::table('cartoon_role_lovers')
                ->where('role_id', $roleId)
                ->get()
                ->toArray();

            $result = [];
            foreach ($list as $item)
            {
                $user = $userRepository->item($item->user_id);
                $item->user = $userTransformer->item($user);
                $result[] = $item;
            }

            return $list;
        });
    }

    public function checkHasStar($roleId, $userId)
    {
        if (!$userId)
        {
            return 0;
        }

        $count = CartoonRoleFans
            ::whereRaw('role_id = ? and user_id = ?', [$roleId, $userId])
            ->pluck('star_count')
            ->first();

        return is_null($count) ? 0 : intval($count);
    }

    public function newFansIds($roleId, $minId, $count = null)
    {
        $take = $count ?: config('website.list_count');

        $ids = $this->RedisSort('cartoon_role_' . $roleId . '_new_fans_ids', function () use ($roleId)
        {
            return CartoonRoleFans::where('role_id', $roleId)
                ->orderBy('updated_at', 'desc')
                ->take(100)
                ->pluck('updated_at', 'user_id AS id');

        }, true, true);

        return $this->filterIdsByMaxId($ids, $minId, $take, true);
    }

    public function hotFansIds($roleId, $seenIds)
    {
        $ids = $this->RedisSort('cartoon_role_' . $roleId . '_hot_fans_ids', function () use ($roleId)
        {
            return CartoonRoleFans::where('role_id', $roleId)
                ->orderBy('star_count', 'desc')
                ->take(100)
                ->pluck('star_count', 'user_id AS id');

        }, false, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, config('website.list_count'), true);
    }

    public function idol24HourStockChartData($idolId)
    {
        $data = $this->RedisList($this->idol24HourMarketPrice($idolId), function ()
        {
            return [];
        });

        $result = [];

        foreach ($data as $item)
        {
            $arr = explode('-', $item);
            $result[] = [
                'time' => $arr[0],
                'value' => $arr[1]
            ];
        }

        return $result;
    }

    public function idolSomeDayStockChartData($idolId, $days)
    {
        return $this->Cache("idol_{$days}_days_stock_chart_data", function () use ($idolId, $days)
        {
            $list = DB
                ::table('virtual_idol_day_activity')
                ->where('model_id', $idolId)
                ->where('days', '>=', Carbon::now()->addDays(-$days))
                ->pluck('day', 'value')
                ->toArray();

            $result = [];
            foreach ($list as $item)
            {
                $result[] = [
                    'time' => strtotime($item['day']),
                    'value' => $item['value']
                ];
            }

            return $result;
        });
    }

    public function getIdolProductIds($idolId, $minId, $count = 15)
    {
        $ids = $this->RedisSort($this->stock_product_list_cache_key($idolId), function () use ($idolId)
        {
            return VirtualIdolPorduct
                ::where('result', 1)
                ->when($idolId != 0, function ($query) use ($idolId)
                {
                    return $query->where('idol_id', $idolId);
                })
                ->orderBy('updated_at', 'DESC')
                ->pluck('updated_at', 'product_id AS id');

        }, true);

        return $this->filterIdsByMaxId($ids, $minId, $count);
    }

    public function lastIdolMarketPriceDraftId($idolId)
    {
        return $this->RedisItem($this->lastIdolMarketPriceDraftCacheKey($idolId), function () use ($idolId)
        {
            $id = VirtualIdolPriceDraft
                ::where('idol_id', $idolId)
                ->where('result', 0)
                ->pluck('id')
                ->first();

            if (!$id)
            {
                return 0;
            }

            return $id;
        });
    }

    public function newOwnerIds($roleId, $minId, $count = 20)
    {
        $ids = $this->RedisSort($this->newOwnerIdsCacheKey($roleId), function () use ($roleId)
        {
            return VirtualIdolOwner
                ::where('idol_id', $roleId)
                ->orderBy('updated_at', 'desc')
                ->take(100)
                ->pluck('updated_at', 'user_id AS id');

        }, true, true);

        return $this->filterIdsByMaxId($ids, $minId, $count, true);
    }

    public function bigOwnerIds($roleId, $seenIds, $count = 20)
    {
        $ids = $this->RedisSort($this->bigOwnerIdsCacheKey($roleId), function () use ($roleId)
        {
            return VirtualIdolOwner
                ::where('idol_id', $roleId)
                ->orderBy('stock_count', 'DESC')
                ->orderBy('id', 'ASC')
                ->take(100)
                ->pluck('stock_count', 'user_id AS id');

        }, false, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, $count, true);
    }

    public function setIdolBiggestBoss($roleId)
    {
        $bossId = VirtualIdolOwner
            ::where('idol_id', $roleId)
            ->orderBy('stock_count', 'DESC')
            ->orderBy('id', 'ASC')
            ->pluck('user_id')
            ->first();

        if (is_null($bossId))
        {
            return 0;
        }

        CartoonRole
            ::where('id', $roleId)
            ->update([
                'boss_id' => $bossId,
                'last_edit_at' => null
            ]);

        $cacheKey = $this->idolItemCacheKey($roleId);
        if (Redis::EXISTS($cacheKey))
        {
            Redis::HSET($cacheKey, 'boss_id', $bossId);
            Redis::HSET($cacheKey, 'last_edit_at', null);
        }

        return $bossId;
    }

    public function setIdolMaxStockCount($roleId, $count)
    {
        CartoonRole
            ::where('id', $roleId)
            ->update([
                'max_stock_count' => $count
            ]);

        if (Redis::EXISTS($this->idolItemCacheKey($roleId)))
        {
            Redis::HSET($this->idolItemCacheKey($roleId), 'max_stock_count', $count);
        }
    }

    public function userIdolList($userId, $seenIds, $count)
    {
        $ids = $this->RedisSort($this->userIdolListCacheKey($userId), function () use ($userId)
        {
            return VirtualIdolOwner
                ::where('user_id', $userId)
                ->orderBy('stock_count', 'desc')
                ->pluck('stock_count', 'idol_id AS id');

        }, false, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, $count, true);
    }

    public function bangumiIdolList($bangumiId, $seenIds, $count)
    {
        $ids = $this->RedisSort($this->bangumiIdolListCacheKey($bangumiId), function () use ($bangumiId)
        {
            return CartoonRole
                ::where('bangumi_id', $bangumiId)
                ->orderBy('market_price', 'DESC')
                ->pluck('market_price', 'id');
        }, false, true, 'm');

        return $this->filterIdsBySeenIds($ids, $seenIds, $count, true);
    }

    public function newbieIdolList($sort, $seenIds, $count)
    {
        /**
         * sort
         * newest => 最新创建的
         * star_count => 最多人入股
         */
        $ids = $this->RedisSort($this->newbieIdolListCacheKey($sort), function () use ($sort)
        {
            if ($sort === 'newest')
            {
                return CartoonRole
                    ::where('company_state', 0)
                    ->orderBy('id', 'DESC')
                    ->pluck('id', 'id');
            }
            else if ($sort === 'star_count')
            {
                return CartoonRole
                    ::where('company_state', 0)
                    ->orderBy('star_count', 'DESC')
                    ->pluck('star_count', 'id');
            }
            else
            {
                return [];
            }
        }, false, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, $count, true);
    }

    public function marketIdolList($sort, $seenIds, $count)
    {
        /**
         * sort
         * market_price => 市值最高
         * fans_count => 粉丝最多
         * stock_price => 股价最高
         * newest => 最新上市
         * activity => 最新交易
         */
        $isTime = $sort === 'activity' || $sort === 'newest';
        $ids = $this->RedisSort($this->marketIdolListCacheKey($sort), function () use ($sort)
        {
            if ($sort === 'market_price')
            {
                return CartoonRole
                    ::where('company_state', 1)
                    ->orderBy('market_price', 'DESC')
                    ->pluck('market_price', 'id');
            }
            else if ($sort === 'fans_count')
            {
                return CartoonRole
                    ::where('company_state', 1)
                    ->orderBy('fans_count', 'DESC')
                    ->pluck('fans_count', 'id');
            }
            else if ($sort === 'stock_price')
            {
                return CartoonRole
                    ::where('company_state', 1)
                    ->orderBy('stock_price', 'DESC')
                    ->pluck('stock_price', 'id');
            }
            else if ($sort === 'newest')
            {
                return CartoonRole
                    ::where('company_state', 1)
                    ->where('max_stock_count', '<>', '0.00')
                    ->orderBy('ipo_at', 'DESC')
                    ->pluck('ipo_at', 'id');
            }
            else if ($sort === 'activity')
            {
                return CartoonRole
                    ::where('company_state', 1)
                    ->where('max_stock_count', '<>', '0.00')
                    ->orderBy('updated_at', 'DESC')
                    ->pluck('updated_at', 'id');
            }
            else
            {
                return [];
            }
        }, $isTime, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, $count, true);
    }

    public function marketIdolListCacheKey($sort)
    {
        return "market_virtual_idol_list_{$sort}_ids";
    }

    public function newbieIdolListCacheKey($sort)
    {
        return "newbie_virtual_idol_list_{$sort}_ids";
    }

    public function dealItem($id, $showDeleted = false)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->RedisHash($this->idolDealItemCacheKey($id), function () use ($id)
        {
            $deal = VirtualIdolDeal
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($deal))
            {
                return null;
            }

            return $deal->toArray();
        });

        if (!$result)
        {
            return null;
        }

        if (!$result || ($result['deleted_at'] && !$showDeleted))
        {
            return null;
        }

        return $result;
    }

    public function idolDealIds($seenIds, $count)
    {
        $ids = $this->RedisSort($this->idolDealListCacheKey(), function ()
        {
            return VirtualIdolDeal
                ::orderBy('updated_at', 'desc')
                ->pluck('updated_at', 'id');

        }, true, true);

        $result = $this->filterIdsBySeenIds($ids, $seenIds, $count, true);
        $roleId = [];
        foreach ($result['ids'] as $id => $score)
        {
            $roleId[] = $id;
        }
        $result['ids'] = $roleId;
        return $result;
    }

    public function removeUserCheer($userId)
    {
        $list = VirtualIdolOwner
            ::where('user_id', $userId)
            ->get()
            ->toArray();

        foreach ($list as $item)
        {
            $idolId = $item['idol_id'];
            CartoonRole
                ::where('id', $idolId)
                ->increment('market_price', -$item['total_price']);

            CartoonRole
                ::where('id', $idolId)
                ->increment('star_count', -$item['stock_count']);

            CartoonRole
                ::where('id', $idolId)
                ->increment('fans_count', -1);

            $cacheKey = $this->idolItemCacheKey($idolId);
            Redis::DEL($cacheKey);
            $cacheKey = $this->newOwnerIdsCacheKey($idolId);
            Redis::DEL($cacheKey);
            $cacheKey = $this->bigOwnerIdsCacheKey($idolId);
            Redis::DEL($cacheKey);
        }

        VirtualIdolOwner
            ::where('user_id', $userId)
            ->delete();

        return true;
    }

    public function stockBuyerTotalCount()
    {
        return $this->RedisItem($this->stockBuyerTotolCountKey(), function ()
        {
            return VirtualIdolOwner::count();
        });
    }

    public function stockBuyerTotalMoney()
    {
        return $this->RedisItem($this->stockBuyerTotalMoneyKey(), function ()
        {
            return VirtualIdolOwner::sum('total_price');
        });
    }

    public function stockDealTotalCount()
    {
        return $this->RedisItem($this->stockDealTotalCountCacheKey(), function ()
        {
            return VirtualIdolDealRecord::count();
        });
    }

    public function stockDealTotalMoney()
    {
        return $this->RedisItem($this->stockDealTotalMoneyCacheKey(), function ()
        {
            return VirtualIdolDealRecord::sum('exchange_amount');
        });
    }

    // 用户的偶像列表缓存 key
    public function userIdolListCacheKey($userId)
    {
        return "user_{$userId}_virtual_idol_list_ids";
    }

    // 番剧的偶像列表缓存 key
    public function bangumiIdolListCacheKey($bangumi_id)
    {
        return "bangumi_{$bangumi_id}_virtual_idol_list_ids";
    }

    // 单个偶像的缓存 key
    public function idolItemCacheKey($roleId)
    {
        return "virtual_idol_{$roleId}";
    }

    // 偶像的最新投资人列表缓存 key
    public function newOwnerIdsCacheKey($roleId)
    {
        return "virtual_idol_{$roleId}_newest_owner_ids";
    }

    // 偶像的大股东列表缓存 key
    public function bigOwnerIdsCacheKey($roleId)
    {
        return "virtual_idol_{$roleId}_biggest_owner_ids";
    }

    // 交易大厅的列表id缓存
    public function idolDealListCacheKey()
    {
        return 'virtual_idol_deal_list_ids';
    }

    // 交易的缓存key
    public function idolDealItemCacheKey($id)
    {
        return "virtual_idol_deal_{$id}";
    }

    // 最近入股的记录列表
    public function recentBuyStockCacheKey()
    {
        return 'virtual_idol_recent_buy_list';
    }

    public function idol24HourMarketPrice($idol_id)
    {
        return "virtual_idol_{$idol_id}_24_hour_market_price";
    }

    // 最近交易的记录列表
    public function recentDealStockCacheKey()
    {
        return 'virtual_idol_recent_deal_list';
    }

    // 投资人总数
    public function stockBuyerTotolCountKey()
    {
        return 'virtual_idol_buyer_total_count';
    }

    // 股市总盘
    public function stockBuyerTotalMoneyKey()
    {
        return 'virtual_idol_buyer_total_money';
    }

    // 总交易笔数
    public function stockDealTotalCountCacheKey()
    {
        return 'virtual_idol_deal_total_count';
    }

    // 总交易额
    public function stockDealTotalMoneyCacheKey()
    {
        return 'virtual_idol_deal_total_money';
    }

    // 偶像最后一个正在投票的提案的id缓存
    public function lastIdolMarketPriceDraftCacheKey($idolId)
    {
        return "virtual_idol_{$idolId}_last_market_price_draft_id";
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $role = $this->item($id);
        $content = $role['name'] . '|' . $role['intro'];

        $job = (new \App\Jobs\Search\Index($type, 'role', $id, $content));
        dispatch($job);
    }

    public function stock_product_list_cache_key($idol_id)
    {
        return "virtual_idol_{$idol_id}_market_product_ids";
    }
}