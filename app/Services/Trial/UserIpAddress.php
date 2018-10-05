<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/29
 * Time: 下午12:20
 */

namespace App\Services\Trial;


use Illuminate\Support\Facades\DB;

class UserIpAddress
{
    public function add($ipAddress, $userId)
    {
        DB::table('user_ip')
            ->insert([
                'ip_address' => $ipAddress,
                'user_id' => $userId
            ]);
    }

    public function userIps($userId)
    {
        return DB::table('user_ip')
            ->where('user_id', $userId)
            ->select('ip_address', 'created_at')
            ->get()
            ->toArray();
    }

    public function addressUsers($ipAddress)
    {
        return DB::table('user_ip')
            ->where('ip_address', $ipAddress)
            ->select('user_id', 'created_at')
            ->get()
            ->toArray();
    }
}