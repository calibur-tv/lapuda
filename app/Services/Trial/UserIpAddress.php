<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/29
 * Time: 下午12:20
 */

namespace App\Services\Trial;


use App\Api\V1\Repositories\Repository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UserIpAddress
{
    public function add($ipAddress, $userId, $blocked = 0)
    {
        $now = Carbon::now();

        DB
            ::table('user_ip')
            ->insert([
                'ip_address' => $ipAddress,
                'user_id' => $userId,
                'blocked' => $blocked,
                'created_at' => $now,
                'updated_at' => $now
            ]);
    }

    public function userIps($userId)
    {
        return DB
            ::table('user_ip')
            ->where('user_id', $userId)
            ->select('ip_address', 'created_at', 'blocked', 'updated_at')
            ->get()
            ->toArray();
    }

    public function addressUsers($ipAddress)
    {
        return DB
            ::table('user_ip')
            ->where('ip_address', $ipAddress)
            ->distinct()
            ->pluck('user_id');
    }

    public function matrixUserIp($page = 0, $count = 15)
    {
        $repository = new Repository();

        $ip = $repository->RedisSort('matrix-user-ids', function ()
        {
            $data = DB
                ::table('user_ip')
                ->where('ip_address', '<>', '')
                ->select(DB::raw('count(distinct user_id) as count, ip_address'))
                ->orderBy('count', 'DESC')
                ->groupBy('ip_address')
                ->pluck('count', 'ip_address')
                ->toArray();

            $result = [];
            foreach ($data as $key => $val)
            {
                $result[preg_replace('/\./', '-', $key)] = $val;
            }

            return $result;

        }, $isTime = false, $withScore = true, $exp = 'h');

        $result = $repository->filterIdsByPage($ip, $page, $count);
        $ids = [];
        foreach ($result['ids'] as $key => $val)
        {
            $ids[preg_replace('/-/', '.', $key)] = $val;
        }
        $result['ids'] = $ids;

        return $result;
    }

    public function blockedList()
    {
        $repository = new Repository();

        return $repository->RedisList('blocked_user_ips', function ()
        {
            return DB
                ::table('user_ip')
                ->where('blocked', 1)
                ->pluck('ip_address')
                ->toArray();
        });
    }

    public function blockUserByIp($ipAddress)
    {
        $userIds = DB
            ::table('user_ip')
            ->where('ip_address', $ipAddress)
            ->pluck('user_id');

        if (empty($userIds))
        {
            $this->add($ipAddress, 0, 1);
        }
        else
        {
            DB
                ::table('user_ip')
                ->whereIn('user_id', $userIds)
                ->update([
                    'blocked' => 1
                ]);
        }

        Redis::DEL('blocked_user_ips');
        Redis::DEL('blocked_user_ids');
    }

    public function recoverUser($ipAddress)
    {
        $userId = DB
            ::table('user_ip')
            ->where('ip_address', $ipAddress)
            ->pluck('user_id')
            ->first();

        if (is_null($userId))
        {
            return;
        }

        if ($userId)
        {
            DB
                ::table('user_ip')
                ->where('user_id', $userId)
                ->update([
                    'blocked' => 0
                ]);
        }
        else
        {
            DB
                ::table('user_ip')
                ->where('ip_address', $ipAddress)
                ->delete();
        }

        Redis::DEL('blocked_user_ips');
        Redis::DEL('blocked_user_ids');
    }

    public function check($userId)
    {
        if (!$userId)
        {
            return false;
        }

        $repository = new Repository();

        $ids = $repository->RedisList('blocked_user_ids', function ()
        {
            return DB
                ::table('user_ip')
                ->where('blocked', 1)
                ->pluck('user_id')
                ->toArray();
        });

        if (empty($ids))
        {
            return false;
        }

        return in_array($userId, $ids);
    }

    public function checkIpIsBlocked($ipAddress)
    {
        if (!$ipAddress)
        {
            return false;
        }

        return (boolean)DB::table('user_ip')
            ->where('ip_address', $ipAddress)
            ->pluck('blocked')
            ->first();
    }
}