<?php

namespace App\Api\V1\Services\Trending\Base;

use App\Api\V1\Repositories\Repository;
use Illuminate\Support\Facades\Redis;

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
    protected $timeout = 1800; // 30 分钟重算一次

    public function __construct($table, $bangumiId, $userId)
    {
        $this->table = $table;
        $this->bangumiId = $bangumiId;
        $this->userId = $userId;
    }

    public function news($minId, $take)
    {
        $idsObject = $this->getNewsIds($minId, $take);
        $list = $this->getListByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function active($seenIds, $take)
    {
        $idsObject = $this->getActiveIds($seenIds, $take);
        $list = $this->getListByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function hot($seenIds, $take)
    {
        $idsObject = $this->getHotIds($seenIds, $take);
        $list = $this->getListByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function users($page, $take)
    {
        $idsObject = $this->getUserIds($page, $take);
        $list = $this->getListByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function getNewsIds($maxId, $take)
    {
        $this->deleteCacheIfTimeout('news');
        // 动态有序，使用 minId 的 list
        $ids = $this->RedisList($this->trendingIdsCacheKey('news', $this->bangumiId), function ()
        {
            $ids = $this->computeNewsIds();

            $this->refreshTimeout('news');

            return $ids;
        });

        return $this->filterIdsByMaxId($ids, $maxId, $take);
    }

    public function getActiveIds($seenIds, $take)
    {
        $this->deleteCacheIfTimeout('active');
        // 动态无序，使用 seenIds 的 sort set
        $ids = $this->RedisSort($this->trendingIdsCacheKey('active', $this->bangumiId), function ()
        {
            $ids = $this->computeActiveIds();

            $this->refreshTimeout('active');

            return $ids;
        }, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function getHotIds($seenIds, $take)
    {
        $this->deleteCacheIfTimeout('hot');
        // 动态无序，使用 seenIds 的 sort set
        $ids = $this->RedisSort($this->trendingIdsCacheKey('hot', $this->bangumiId), function ()
        {
            $ids = $this->computeHotIds();

            $this->refreshTimeout('hot');

            return $ids;
        });

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function getUserIds($page, $take)
    {
        $ids = $this->RedisList($this->trendingFlowUsersKey(), function ()
        {
            return $this->computeUserIds();
        });

        return $this->filterIdsByPage($ids, $page, $take);
    }

    public function create($id)
    {
        if (gettype($this->bangumiId) === 'array')
        {
            foreach ($this->bangumiId as $bid)
            {
                $this->ListInsertBefore($this->trendingIdsCacheKey('news', $bid), $id);
                $this->SortAdd($this->trendingIdsCacheKey('active', $bid), $id);
            }
        }
        else
        {
            $this->ListInsertBefore($this->trendingIdsCacheKey('news', $this->bangumiId), $id);
            $this->SortAdd($this->trendingIdsCacheKey('active', $this->bangumiId), $id);
        }
        $this->ListInsertBefore($this->trendingIdsCacheKey('news', 0), $id);
        $this->SortAdd($this->trendingIdsCacheKey('active', 0), $id);
        $this->ListInsertBefore($this->trendingFlowUsersKey(), $id);
    }

    public function update($id)
    {
        if (gettype($this->bangumiId) === 'array')
        {
            foreach ($this->bangumiId as $bid)
            {
                $this->SortAdd($this->trendingIdsCacheKey('active', $bid), $id);
            }
        }
        else
        {
            $this->SortAdd($this->trendingIdsCacheKey('active', $this->bangumiId), $id);
        }
        $this->SortAdd($this->trendingIdsCacheKey('active', 0), $id);
    }

    public function delete($id)
    {
        if (gettype($this->bangumiId) === 'array')
        {
            foreach ($this->bangumiId as $bid)
            {
                $this->ListRemove($this->trendingIdsCacheKey('news', $bid), $id);
                $this->SortRemove($this->trendingIdsCacheKey('active', $bid), $id);
                $this->SortRemove($this->trendingIdsCacheKey('hot', $bid), $id);
            }
        }
        else
        {
            $this->ListRemove($this->trendingIdsCacheKey('news', $this->bangumiId), $id);
            $this->SortRemove($this->trendingIdsCacheKey('active', $this->bangumiId), $id);
            $this->SortRemove($this->trendingIdsCacheKey('hot', $this->bangumiId), $id);
        }
        $this->ListRemove($this->trendingIdsCacheKey('news', 0), $id);
        $this->SortRemove($this->trendingIdsCacheKey('active', 0), $id);
        $this->SortRemove($this->trendingIdsCacheKey('hot', 0), $id);
        $this->ListRemove($this->trendingFlowUsersKey(), $id);
    }

    public function getListByIds($ids)
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

    protected function deleteCacheIfTimeout($type)
    {
        if (
            !Redis::EXISTS($this->checkTimeoutCacheKey($type, $this->bangumiId)) ||
            time() - Redis::EXISTS($this->checkTimeoutCacheKey($type, $this->bangumiId)) > $this->timeout
        )
        {
            Redis::DEL($this->trendingIdsCacheKey($type, $this->bangumiId));
        }
    }

    protected function refreshTimeout($type)
    {
        $this->RedisItem($this->checkTimeoutCacheKey($type, $this->bangumiId), function ()
        {
            return time();
        });
    }

    protected function trendingFlowUsersKey()
    {
        return 'trending_' . $this->table . '_user_' . $this->userId . '_created_ids';
    }

    protected function trendingIdsCacheKey($type, $bangumiId)
    {
        return 'trending_' . $this->table . '_bangumi_' . $bangumiId . '_' . $type . '_ids';
    }

    protected function checkTimeoutCacheKey($type, $bangumiId)
    {
        return 'trending_' . $this->table . '_bangumi_' . $bangumiId . '_' . $type . '_timeout';
    }
}