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
    /*
     * 让缓存在第二天凌晨的 1 点到 3点失效，是为了弱化缓存风暴，但并没有解决
     */

    public function RedisHash($key, $func)
    {
        $cache = Redis::HGETALL($key);
        if (empty($cache))
        {
            $cache = $func();

            if (is_null($cache))
            {
                return null;
            }

            Redis::pipeline(function ($pipe) use ($key, $cache)
            {
                $pipe->HMSET($key, gettype($cache) === 'array' ? $cache : $cache->toArray());
                $pipe->EXPIREAT($key, $this->expire());
            });

            return $cache;
        }
        else
        {
            return $cache;
        }
    }

    public function RedisList($key, $func, $start = 0, $count = -1)
    {
        $cache = Redis::LRANGE($key, $start, $count === -1 ? -1 : $count + $start - 1);
        if (empty($cache))
        {
            $cache = $func();
            $cache = gettype($cache) === 'array' ? $cache : $cache->toArray();

            if (empty($cache))
            {
                return [];
            }

            Redis::pipeline(function ($pipe) use ($key, $cache)
            {
                $pipe->DEL($key);
                $pipe->RPUSH($key, $cache);
                $pipe->EXPIREAT($key, $this->expire());
            });

            return $count === -1 ? array_slice($cache, $start) : array_slice($cache, $start, $count);
        }
        else
        {
            return $cache;
        }
    }

    public function RedisSort($key, $func, $isTime = false)
    {
        $cache = Redis::ZREVRANGE($key, 0, -1);
        if (empty($cache))
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

            Redis::pipeline(function ($pipe) use ($key, $cache)
            {
                $pipe->DEL($key);
                $pipe->ZADD($key, $cache);
                $pipe->EXPIREAT($key, $this->expire());
            });

            return array_keys($cache);
        }
        else
        {
            return $cache;
        }
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