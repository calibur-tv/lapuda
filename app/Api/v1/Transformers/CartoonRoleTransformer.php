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
    public function show($data)
    {
        return $this->transformer($data, function ($role)
        {
            return [
                'bangumi' => $role['bangumi'],
                'fans' => $role['fans'],
                'images' => $role['images'],
                'data' => $this->transformer($role['data'], function ($info)
                {
                    return [
                        'alias' => $info['alias'],
                        'avatar' => $info['avatar'],
                        'fans_count' => (int)$info['fans_count'],
                        'hasStar' => (int)$info['hasStar'],
                        'intro' => $info['intro'],
                        'lover' => $info['lover'],
                        'name' => $info['name'],
                        'star_count' => (int)$info['star_count']
                    ];
                }),
            ];
        });
    }

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
                }) : null,
                'has_star' => $role['hasStar']
            ];
        });
    }

    public function fans($users)
    {
        return $this->collection($users, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname'],
                'score' => (int)$user['score']
            ];
        });
    }

    public function trending($list)
    {
        return $this->collection($list, function ($user)
        {
            $result = [
                'id' => (int)$user['id'],
                'avatar' => $user['avatar'],
                'name' => $user['name'],
                'intro' => $user['intro'],
                'star_count' => (int)$user['star_count'],
                'fans_count' => (int)$user['fans_count'],
                'bangumi_id' => (int)$user['bangumi_id'],
                'bangumi_avatar' => $user['bangumi_avatar'],
                'bangumi_name' => $user['bangumi_name'],
                'lover_id' => (int)$user['lover_id']
            ];

            if ($user['lover_id'])
            {
                $result = array_merge($result, [
                    'lover_avatar' => $user['lover_avatar'],
                    'lover_nickname' => $user['lover_nickname'],
                    'lover_zone' => $user['lover_zone']
                ]);
            }

            return $result;
        });
    }

    public function userList($list)
    {
        return $this->collection($list, function ($role)
        {
            return [
                'id' => (int)$role['id'],
                'name' => $role['name'],
                'avatar' => $role['avatar'],
                'intro' => $role['intro'],
                'star_count' => (int)$role['star_count'],
                'fans_count' => (int)$role['fans_count'],
                'has_star' => (int)$role['has_star'],
                'bangumi_id' => (int)$role['bangumi_id']
            ];
        });
    }
}