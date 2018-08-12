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
                'data' => $this->transformer($role['data'], function ($info)
                {
                    return [
                        'id' => (int)$info['id'],
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
                'alias' => $role['alias'],
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

    public function search($data)
    {
        return $this->transformer($data, function ($role)
        {
            return [
                'id' => (int)$role['id'],
                'name' => $role['name'],
                'avatar' => $role['avatar'],
                'intro' => $role['intro']
            ];
        });
    }

    public function trending($list)
    {
        return $this->collection($list, function ($item)
        {
            $result = [
                'id' => (int)$item['id'],
                'avatar' => $item['avatar'],
                'name' => $item['name'],
                'intro' => $item['intro'],
                'star_count' => (int)$item['star_count'],
                'fans_count' => (int)$item['fans_count'],
                'bangumi' => $this->transformer($item['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                })
            ];

            if ($item['loverId'])
            {
                $result = array_merge($result, [
                    'lover' => [
                        'id' => $item['loverId'],
                        'avatar' => $item['lover_avatar'],
                        'nickname' => $item['lover_nickname'],
                        'zone' => $item['lover_zone']
                    ]
                ]);
            }
            else
            {
                $result = array_merge($result, [
                    'lover' => null
                ]);
            }

            return $result;
        });
    }
}