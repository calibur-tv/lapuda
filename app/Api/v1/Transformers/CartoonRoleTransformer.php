<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午11:36
 */

namespace App\Api\V1\Transformers;


class CartoonRoleTransformer extends Transformer
{
    public function bangumi($list)
    {
        return $this->collection($list, function ($role)
        {
            return [
                'id' => (int)$role['id'],
                'name' => $role['name'],
                'avatar' => $role['avatar'],
                'loverId' => (int)$role['loverId'],
                'intro' => $role['intro'],
                'star_count' => (int)$role['star_count'],
                'fans_count' => (int)$role['fans_count'],
                'loveMe' => (boolean)$role['loveMe'],
                'lover' => $role['lover'] ? $this->transformer($role['lover'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'avatar' => $user['avatar'],
                        'zone' => $user['zone'],
                        'nickname' => $user['nickname']
                    ];
                }) : null
            ];
        });
    }
}