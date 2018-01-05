<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午9:21
 */

namespace App\Api\V1\Transformers;


class UserTransformer
{
    public function item($user)
    {
        return [
            'id' => (int)$user['id'],
            'zone' => $user['zone'],
            'avatar' => $user['avatar'],
            'nickname' => $user['nickname']
        ];
    }
}