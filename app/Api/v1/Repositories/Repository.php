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
                $pipe->DEL('lock_'.$key);
            });
        }

        return $count === -1 ? array_slice($cache, $start) : array_slice($cache, $start, $count);
    }

    public function RedisSort($key, $func, $isTime = false, $force = false, $withScore = false, $exp = 'd')
    {
        $cache = $withScore ? Redis::ZREVRANGE($key, 0, -1, 'WITHSCORES') : Redis::ZREVRANGE($key, 0, -1);

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
                Redis::pipeline(function ($pipe) use ($key, $cache, $exp)
                {
                    $pipe->EXPIRE('lock_'.$key, 10);
                    $pipe->DEL($key);
                    $pipe->ZADD($key, $cache);
                    $pipe->EXPIREAT($key, $this->expire($exp));
                    $pipe->DEL('lock_'.$key);
                });
            }

            return $withScore ? $cache : array_keys($cache);
        }

        return $cache;
    }

    public function RedisHash($key, $func, $exp = 'd')
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
            Redis::pipeline(function ($pipe) use ($key, $cache, $exp)
            {
                $pipe->EXPIRE('lock_'.$key, 10);
                $pipe->HMSET($key, gettype($cache) === 'array' ? $cache : $cache->toArray());
                $pipe->EXPIREAT($key, $this->expire($exp));
                $pipe->DEL('lock_'.$key);
            });
        }

        return $cache;
    }

    public function Cache($key, $func, $exp = 'd')
    {
        return Cache::remember($key, $this->expiredAt($exp), function () use ($func)
        {
            return $func();
        });
    }

    private function expiredAt($type = 'd')
    {
        if ($type === 'd')
        {
            return 720;
        }
        else if ($type === 'h')
        {
            return 60;
        }
        else if ($type === 'm')
        {
            return 5;
        }

        return 86400;
    }

    private function expire($type = 'd')
    {
        /**
         * d：缓存一天，第二天凌晨的 1 ~ 3 点删除
         * h：缓存一小时
         * m：缓存五分钟
         */
        $day = strtotime(date('Y-m-d')) + 86400 + rand(3600, 10800);
        $hour = strtotime(date('Y-m-d')) + 3600;
        $minute = strtotime(date('Y-m-d')) + 300;

        if ($type === 'd')
        {
            return $day;
        }
        else if ($type === 'h')
        {
            return $hour;
        }
        else if ($type === 'm')
        {
            return $minute;
        }

        return $day;
    }
}