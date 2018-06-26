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
                'birthday' => (int)$user['birthday'],
                'sex' => $user['sex'],
                'signature' => $user['signature'],
                'uptoken' => $user['uptoken'],
                'daySign' => (boolean)$user['daySign'],
                'coin' => (int)$user['coin_count'],
                'faker' => (boolean)$user['faker'],
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