<?php

namespace App\Api\V1\Services\Counter;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 上午8:32
 */
class CounterService
{
    /**
     * 如果 cache 被删了，数据可以恢复吗
     * 像访问数统计，因为没有单独的表，所以无法恢复
     * 但是像关注数，因为有单独的关注表，所以能恢复
     *
     * 什么时候需要恢复？set or get？how？
     * get 的时候恢复，在外层实现 migrate 方法
     */
    protected $id;
    protected $table;
    protected $field;
    protected $timeout = 60;

    public function __construct($tableName, $filedName)
    {
        $this->table = $tableName;
        $this->field = $filedName;
    }

    public function get($id)
    {
        $this->id = $id;
        $cacheKey = $this->cacheKey($id);

        if (Redis::EXISTS($cacheKey))
        {
            return Redis::get($cacheKey);
        }

        $count = $this->migrate($id);
        if (false === $count)
        {
            $count = DB::table($this->table)
                ->where('id', $id)
                ->pluck($this->field)
                ->first();
        }

        $this->writeCache($cacheKey, $count);
        $this->writeCache($this->writeKey($id), time());

        return $count;
    }

    public function batchGet($list, $key)
    {
        foreach ($list as $i => $item)
        {
            $list[$i][$key] = $this->get($item['id']);
        }

        return $list;
    }

    public function add($id, $num = 1)
    {
        $this->id = $id;
        $cacheKey = $this->cacheKey($id);

        if (Redis::EXISTS($cacheKey))
        {
            $result = Redis::INCRBY($cacheKey, $num);
            $writeKey = $this->writeKey($id);

            if (
                !Redis::EXISTS($writeKey) ||
                time() - Redis::get($writeKey) > $this->timeout
            )
            {
                DB::table($this->table)
                    ->where('id', $id)
                    ->update([
                        $this->field => $result
                    ]);

                $this->writeCache($writeKey, time());
            }

            return $result;
        }

        DB::table($this->table)
            ->where('id', $id)
            ->increment($this->field, $num);

        return $this->get($id);
    }

    public function migrate($id)
    {
        return false;
    }

    protected function writeCache($key, $value)
    {
        Redis::set($key, $value);
        Redis::EXPIRE($key, $this->timeout * 2);
    }

    protected function cacheKey($id)
    {
        return $this->table . '_' . $id . '_' . $this->field;
    }

    protected function writeKey($id)
    {
        return $this->table . '_' . $id . '_' . $this->field . '_' . 'last_add_at';
    }
}