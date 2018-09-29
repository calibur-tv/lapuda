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

    }

    public function addressUsers($ipAddress)
    {

    }
}