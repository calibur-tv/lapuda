<?php

namespace App\Api\V1\Services\Trending\Base;

use App\Api\V1\Repositories\Repository;
use App\Api\V1\Services\Activity\BangumiActivity;
use App\Api\V1\Services\Activity\UserActivity;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/3
 * Time: 上午10:32
 */
class TrendingService extends Repository
{
    protected $table;
    protected $bangumiId;
    protected $userId;
    protected $cachePrefix;

    public function __construct($table, $bangumiId, $userId, $cachePrefix = 'bangumi')
    {
        $this->table = $table;
        $this->bangumiId = $bangumiId;
        $this->userId = $userId;
        $this->cachePrefix = $cachePrefix;
    }

    public function news($minId, $take)
    {
        $idsObject = $this->getNewsIds($minId, $take);
        $list = $this->getListByIds($idsObject['ids'], $this->bangumiId ? 'bangumi' : 'trending');

        if ($this->userId)
        {
            $userActivityService = new UserActivity();
            $userActivityService->update($this->userId);
        }
        if ($this->bangumiId)
        {
            $bangumiActivity = new BangumiActivity();
            $bangumiActivity->update($this->bangumiId);
        }

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function active($seenIds, $take)
    {
        $idsObject = $this->getActiveIds($seenIds, $take);
        $list = $this->getListByIds($idsObject['ids'], $this->bangumiId ? 'bangumi' : 'trending');

        if ($this->userId)
        {
            $userActivityService = new UserActivity();
            $userActivityService->update($this->userId);
        }
        if ($this->bangumiId)
        {
            $bangumiActivity = new BangumiActivity();
            $bangumiActivity->update($this->bangumiId);
        }

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function hot($seenIds, $take)
    {
        $idsObject = $this->getHotIds($seenIds, $take);
        $list = $this->getListByIds($idsObject['ids'], $this->bangumiId ? 'bangumi' : 'trending');

        if ($this->userId)
        {
            $userActivityService = new UserActivity();
            $userActivityService->update($this->userId);
        }
        if ($this->bangumiId)
        {
            $bangumiActivity = new BangumiActivity();
            $bangumiActivity->update($this->bangumiId);
        }

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function users($page, $take)
    {
        $idsObject = $this->getUserIds($page, $take);
        $list = $this->getListByIds($idsObject['ids'], 'user');

        if ($this->userId)
        {
            $userActivityService = new UserActivity();
            $userActivityService->update($this->userId);
        }

        if ($page == 0 && count($list) == 0)
        {
            $idsObject['total'] = 0;
        }

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function getNewsIds($maxId, $take)
    {
        // 动态有序，使用 minId 的 list
        $ids = $this->RedisList($this->trendingIdsCacheKey('news', $this->bangumiId), function ()
        {
            return $this->computeNewsIds();
        });

        return $this->filterIdsByMaxId($ids, $maxId, $take);
    }

    public function getActiveIds($seenIds, $take)
    {
        // 动态无序，使用 seenIds 的 sort set
        $ids = $this->RedisSort($this->trendingIdsCacheKey('active', $this->bangumiId), function ()
        {
            return $this->computeActiveIds();
        }, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function getHotIds($seenIds, $take)
    {
        // 动态无序，使用 seenIds 的 sort set
        $ids = $this->RedisSort($this->trendingIdsCacheKey('hot', $this->bangumiId), function ()
        {
            return $this->computeHotIds();
        });

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function getUserIds($page, $take)
    {
        $ids = $this->RedisSort($this->trendingFlowUsersKey_v2(), function ()
        {
            return $this->computeUserIds();
        }, true);

        return $this->filterIdsByPage($ids, $page, $take);
    }

    public function create($id, $publish = true)
    {
        if ($publish)
        {
            if (gettype($this->bangumiId) === 'array')
            {
                foreach ($this->bangumiId as $bid)
                {
                    // $this->ListInsertBefore($this->trendingIdsCacheKey('news', $bid), $id);
                    // $this->SortAdd($this->trendingIdsCacheKey('active', $bid), $id);
                    $this->SortAdd($this->trendingIdsCacheKey('hot', $bid), $id);
                }
            }
            else
            {
                // $this->ListInsertBefore($this->trendingIdsCacheKey('news', $this->bangumiId), $id);
                // $this->SortAdd($this->trendingIdsCacheKey('active', $this->bangumiId), $id);
                $this->SortAdd($this->trendingIdsCacheKey('hot', $this->bangumiId), $id);
            }
            // $this->ListInsertBefore($this->trendingIdsCacheKey('news', 0), $id);
            // $this->SortAdd($this->trendingIdsCacheKey('active', 0), $id);
            $this->SortAdd($this->trendingIdsCacheKey('hot', 0), $id);
        }
        $this->SortAdd($this->trendingFlowUsersKey_v2(), $id);
    }

    public function update($id)
    {
        if (gettype($this->bangumiId) === 'array')
        {
            foreach ($this->bangumiId as $bid)
            {
                // $this->SortAdd($this->trendingIdsCacheKey('active', $bid), $id);
                $this->SortAdd($this->trendingIdsCacheKey('hot', $bid), $id);
            }
        }
        else if ($this->checkCanUpdateBangumiIds($id))
        {
            // $this->SortAdd($this->trendingIdsCacheKey('active', $this->bangumiId), $id);
            $this->SortAdd($this->trendingIdsCacheKey('hot', $this->bangumiId), $id);
        }
        // $this->SortAdd($this->trendingIdsCacheKey('active', 0), $id);
        $this->SortAdd($this->trendingIdsCacheKey('hot', 0), $id);
    }

    public function checkCanUpdateBangumiIds($id)
    {
        return true;
    }

    public function delete($id)
    {
        if (gettype($this->bangumiId) === 'array')
        {
            foreach ($this->bangumiId as $bid)
            {
//                $this->ListRemove($this->trendingIdsCacheKey('news', $bid), $id);
//                $this->SortRemove($this->trendingIdsCacheKey('active', $bid), $id);
                $this->SortRemove($this->trendingIdsCacheKey('hot', $bid), $id);
            }
        }
        else
        {
//            $this->ListRemove($this->trendingIdsCacheKey('news', $this->bangumiId), $id);
//            $this->SortRemove($this->trendingIdsCacheKey('active', $this->bangumiId), $id);
            $this->SortRemove($this->trendingIdsCacheKey('hot', $this->bangumiId), $id);
        }
//        $this->ListRemove($this->trendingIdsCacheKey('news', 0), $id);
//        $this->SortRemove($this->trendingIdsCacheKey('active', 0), $id);
        $this->SortRemove($this->trendingIdsCacheKey('hot', 0), $id);
        $this->SortRemove($this->trendingFlowUsersKey_v2(), $id);
    }

    public function deleteIndex($id)
    {
        $this->SortRemove($this->trendingIdsCacheKey('hot', 0), $id);
    }

    public function getListByIds($ids, $flowType)
    {
        return [];
    }

    protected function computeNewsIds()
    {
        return [];
    }

    protected function computeActiveIds()
    {
        return [];
    }

    protected function computeHotIds()
    {
        return [];
    }

    protected function computeUserIds()
    {
        return [];
    }

    protected function trendingFlowUsersKey()
    {
        return 'trending_' . $this->table . '_user_' . $this->userId . '_created_ids';
    }

    protected function trendingFlowUsersKey_v2()
    {
        return 'trending_' . $this->table . '_user_' . $this->userId . '_newest_ids';
    }

    protected function trendingIdsCacheKey($type, $bangumiId)
    {
        return 'trending_' . $this->table . '_' . $this->cachePrefix . '_' . $bangumiId . '_' . $type . '_ids';
    }
}