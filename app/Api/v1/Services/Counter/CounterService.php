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
    protected $cacheKey;
    protected $writeKey;
    protected $timeout = 60;

    public function __construct($tableName, $filedName, $id)
    {
        $this->table = $tableName;
        $this->field = $filedName;
        $this->id = $id;
        $this->cacheKey = $tableName . '_' . $id . '_' . $filedName;
        $this->writeKey = $tableName . '_' . $id . '_' . $filedName . '_' . 'last_add_at';
    }

    public function get()
    {
        if (Redis::EXISTS($this->cacheKey))
        {
            return Redis::get($this->cacheKey);
        }

        $count = $this->migrate();
        if (false === $count)
        {
            $count = DB::table($this->table)
                ->where('id', $this->id)
                ->pluck($this->field)
                ->first();
        }

        Redis::set($this->cacheKey, $count);
        Redis::set($this->writeKey, time());

        return $count;
    }

    public function add($num = 1)
    {
        if (Redis::EXISTS($this->cacheKey))
        {
            $result = Redis::INCRBY($this->cacheKey, $num);

            if (
                !Redis::EXISTS($this->writeKey) ||
                time() - Redis::get($this->writeKey) > $this->timeout
            )
            {
                DB::table($this->table)
                    ->where('id', $this->id)
                    ->update([
                        $this->field => $result
                    ]);

                Redis::set($this->writeKey, time());
            }

            return $result;
        }

        DB::table($this->table)
            ->where('id', $this->id)
            ->increment($this->field, $num);

        return $this->get();
    }

    public function migrate()
    {
        return false;
    }
}