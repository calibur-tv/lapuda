<?php

namespace App\Api\V1\Services\Trending;

use App\Api\V1\Repositories\Repository;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/3
 * Time: 上午10:32
 */
class TrendingService extends Repository
{
    protected $table;

    public function __construct($table)
    {
        $this->table = $table;
    }

    public function getNewsIds($maxId, $take)
    {
        // 动态有序，使用 minId 的 list
        $ids = $this->RedisList($this->trendingIdsCacheKey('news'), function ()
        {
           return $this->computeNewsIds();
        });

        return $this->filterIdsByMaxId($ids, $maxId, $take);
    }

    public function getActiveIds($seenIds, $take)
    {
        // 动态无序，使用 seenIds 的 sort set
        $ids = $this->RedisSort($this->trendingIdsCacheKey('active'), function ()
        {
            return $this->computeActiveIds();
        }, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function getHotIds($seenIds, $take)
    {
        // 动态无序，使用 seenIds 的 sort set
        $ids = $this->RedisSort($this->trendingIdsCacheKey('hot'), function ()
        {
            return $this->computeHotIds();
        });

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function create($id)
    {
        $this->ListInsertBefore($this->trendingIdsCacheKey('news'), $id);
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

    protected function trendingIdsCacheKey($type)
    {
        return 'trending_' . $this->table . '_' . $type . '_ids';
    }
}