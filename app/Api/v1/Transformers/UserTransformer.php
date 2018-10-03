<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午9:21
 */

namespace App\Api\V1\Transformers;


class UserTransformer extends Transformer
{
    public function self($user)
    {
        return $this->transformer($user, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'banner' => $user['banner'],
                'nickname' => $user['nickname'],
                'birthday' => $user['birthday'] ? $user['birthday'] : 0,
                'birthSecret' => (boolean)$user['birth_secret'],
                'sex' => $user['sex'],
                'sexSecret' => (boolean)$user['sex_secret'],
                'signature' => $user['signature'],
                'uptoken' => $user['uptoken'],
                'daySign' => (boolean)$user['daySign'],
                'coin' => (int)$user['coin_count'],
                'faker' => (boolean)$user['faker'],
                'is_admin' => (boolean)$user['is_admin'],
                'notification' => $user['notification']
            ];
        });
    }

    public function card($user)
    {
        return [
            'id' => (int)$user['id'],
            'zone' => $user['zone'],
            'avatar' => $user['avatar'],
            'banner' => $user['banner'],
            'nickname' => $user['nickname'],
            'sex' => $user['sex_secret'] ? "未知" : $user['sex'],
            'birthSecret' => (boolean)$user['birth_secret'],
            'sexSecret' => (boolean)$user['sex_secret'],
            'signature' => $user['signature']
        ];
    }

    public function refresh($user)
    {
        return $this->transformer($user, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'banner' => $user['banner'],
                'nickname' => $user['nickname'],
                'birthday' => $user['birthday'] ? $user['birthday'] : 0,
                'sex' => $user['sex'],
                'birthSecret' => (boolean)$user['birth_secret'],
                'sexSecret' => (boolean)$user['sex_secret'],
                'signature' => $user['signature'],
                'uptoken' => $user['uptoken'],
                'daySign' => (boolean)$user['daySign'],
                'coin' => (int)$user['coin_count'],
                'coin_from_sign' => (int)$user['coin_from_sign'],
                'faker' => (boolean)$user['faker'],
                'is_admin' => (boolean)$user['is_admin'],
                'notification' => $user['notification']
            ];
        });
    }

    public function item($user)
    {
        return $this->transformer($user, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname']
            ];
        });
    }

    public function show($user)
    {
        return $this->transformer($user, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'banner' => $user['banner'],
                'nickname' => $user['nickname'],
                'signature' => $user['signature'],
                'sex' => $user['sex'],
                'sexSecret' => (boolean)$user['sex_secret'],
                'faker' => (boolean)$user['faker']
            ];
        });
    }

    public function list($users)
    {
        return $this->collection($users, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname']
            ];
        });
    }

    public function recommended($users)
    {
        return $this->collection($users, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname'],
                'desc' => $user['desc']
            ];
        });
    }

    public function notification($list)
    {
        return $this->collection($list, function ($data)
        {
            return [
                'id' => (int)$data['id'],
                'user' => $this->transformer($data['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'nickname' => $user['nickname'],
                        'zone' => $user['zone']
                    ];
                }),
                'type' => (int)$data['type'],
                'model' => $data['model'],
                'data' => $this->transformer($data['data'], function ($model)
                {
                    return [
                        'resource' => $model['resource'],
                        'link' => $model['link'],
                        'title' => $model['title']
                    ];
                }),
                'checked' => (boolean)$data['checked']
            ];
        });
    }

    public function toggleUsers($users)
    {
        return $this->collection($users, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname'],
                'created_at' => (int)$user['created_at']
            ];
        });
    }

    public function search($user)
    {
        return $this->transformer($user, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname'],
                'signature' => $user['signature']
            ];
        });
    }
}