<?php

namespace App\Api\V1\Services\Trending;

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
    protected $timeout = 60; // 1 分钟重算一次

    public function __construct($table, $bangumiId)
    {
        $this->table = $table;
        $this->bangumiId = $bangumiId;
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

    public function getNewsIds($maxId, $take)
    {
        $this->deleteCacheIfTimeout('news');
        // 动态有序，使用 minId 的 list
        $ids = $this->RedisList($this->trendingIdsCacheKey('news'), function ()
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
        $ids = $this->RedisSort($this->trendingIdsCacheKey('active'), function ()
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
        $ids = $this->RedisSort($this->trendingIdsCacheKey('hot'), function ()
        {
            $ids = $this->computeHotIds();

            $this->refreshTimeout('hot');

            return $ids;
        });

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function create($id)
    {
        $this->ListInsertBefore($this->trendingIdsCacheKey('news'), $id);
        $this->SortAdd($this->trendingIdsCacheKey('active'), $id);
    }

    public function update($id)
    {
        $this->SortAdd($this->trendingIdsCacheKey('active'), $id);
    }

    public function delete($id)
    {
        $this->ListRemove($this->trendingIdsCacheKey('news'), $id);
        $this->SortRemove($this->trendingIdsCacheKey('active'), $id);
        $this->SortRemove($this->trendingIdsCacheKey('hot'), $id);
    }

    protected function getListByIds($ids)
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

    protected function deleteCacheIfTimeout($type)
    {
        if (
            !Redis::EXISTS($this->checkTimeoutCacheKey($type)) ||
            time() - Redis::EXISTS($this->checkTimeoutCacheKey($type)) > $this->timeout
        )
        {
            Redis::DEL($this->trendingIdsCacheKey($type));
        }
    }

    protected function refreshTimeout($type)
    {
        $this->RedisItem($this->checkTimeoutCacheKey($type), function ()
        {
            return time();
        });
    }

    protected function trendingIdsCacheKey($type)
    {
        return 'trending_' . $this->table . '_bangumi_' . $this->bangumiId . '_' . $type . '_ids';
    }

    protected function checkTimeoutCacheKey($type)
    {
        return 'trending_' . $this->table . '_bangumi_' . $this->bangumiId . '_' . $type . '_timeout';
    }
}