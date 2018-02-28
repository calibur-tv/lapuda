<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/28
 * Time: 上午7:52
 */

namespace App\Api\V1\Repositories;


use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class Repository
{
    public function RedisHash($key, $func)
    {
        $cache = Redis::HGETALL($key);

        if (!empty($cache))
        {
            return $cache;
        }

        $cache = $func();

        if (is_null($cache))
        {
            return null;
        }

        if (Redis::SETNX('lock_'.$key, 1))
        {
            Redis::pipeline(function ($pipe) use ($key, $cache)
            {
                $pipe->EXPIRE('lock_'.$key, 10);
                $pipe->HMSET($key, gettype($cache) === 'array' ? $cache : $cache->toArray());
                $pipe->EXPIREAT($key, $this->expire());
            });
        }

        return $cache;
    }

    public function RedisList($key, $func, $start = 0, $count = -1)
    {
        $cache = Redis::LRANGE($key, $start, $count === -1 ? -1 : $count + $start - 1);

        if (!empty($cache))
        {
            return $cache;
        }

        $cache = $func();
        $cache = gettype($cache) === 'array' ? $cache : $cache->toArray();

        if (empty($cache))
        {
            return [];
        }

        if (Redis::SETNX('lock_'.$key, 1))
        {
            Redis::pipeline(function ($pipe) use ($key, $cache)
            {
                $pipe->EXPIRE('lock_'.$key, 10);
                $pipe->DEL($key);
                $pipe->RPUSH($key, $cache);
                $pipe->EXPIREAT($key, $this->expire());
            });
        }

        return $count === -1 ? array_slice($cache, $start) : array_slice($cache, $start, $count);
    }

    public function RedisSort($key, $func, $isTime = false, $force = false)
    {
        $cache = Redis::ZREVRANGE($key, 0, -1);

        if ($force || empty($cache))
        {
            $cache = $func();
            $cache = gettype($cache) === 'array' ? $cache : $cache->toArray();

            if (empty($cache))
            {
                return [];
            }

            if ($isTime)
            {
                foreach ($cache as $i => $item)
                {
                    $cache[$i] = $item->timestamp;
                }
            }

            if (Redis::SETNX('lock_'.$key, 1))
            {
                Redis::pipeline(function ($pipe) use ($key, $cache)
                {
                    $pipe->EXPIRE('lock_'.$key, 10);
                    $pipe->DEL($key);
                    $pipe->ZADD($key, $cache);
                    $pipe->EXPIREAT($key, $this->expire());
                });
            }

            return array_keys($cache);
        }

        return $cache;
    }

    public function Cache($key, $func, $exp = null)
    {
        return Cache::remember($key, is_null($exp) ? $this->expire() : $exp, function () use ($func)
        {
            return $func();
        });
    }

    private function expire()
    {
        return strtotime(date('Y-m-d')) + 86400 + rand(3600, 10800);
    }
}