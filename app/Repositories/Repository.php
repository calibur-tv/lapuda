<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/28
 * Time: 上午7:52
 */

namespace App\Repositories;


use Illuminate\Support\Facades\Redis;

class Repository
{
    public function RedisHash($key, $func, $expireAt = 0)
    {
        $cache = Redis::HGETALL($key);
        if (empty($cache))
        {
            $cache = $func();

            if (is_null($cache))
            {
                return null;
            }

            Redis::HMSET($key, $cache->toArray());

            $expireAt
                ? Redis::EXPIREAT($key, $expireAt)
                : Redis::EXPIRE($key, config('cache.ttl') * 60);

            return $cache;
        }
        else
        {
            return $cache;
        }
    }

    public function RedisList($key, $func, $start = 0, $stop = -1, $expireAt = 0)
    {
        $cache = Redis::LRANGE($key, $start, $stop);
        if (empty($cache))
        {
            $cache = $func();
            $cache = $cache->toArray();

            if (empty($cache))
            {
                return [];
            }

            Redis::RPUSH($key, $cache);

            $expireAt
                ? Redis::EXPIREAT($key, $expireAt)
                : Redis::EXPIRE($key, config('cache.ttl') * 60);

            return array_slice($cache, $start, $stop);
        }
        else
        {
            return $cache;
        }
    }

    public function RedisSort($key, $func, $expireAt = 0)
    {
        $cache = Redis::ZREVRANGE($key, 0, -1, 'WITHSCORES');
        if (empty($cache))
        {
            $cache = $func();
            $cache = $cache->toArray();

            if (empty($cache))
            {
                return [];
            }

            Redis::ZADD($key, $cache);

            $expireAt
                ? Redis::EXPIREAT($key, $expireAt)
                : Redis::EXPIRE($key, config('cache.ttl') * 60);
        }

        return array_values($cache);
    }
}