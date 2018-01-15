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
                'signature' => $user['signature']
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

    public function notification($data)
    {
        return $this->transformer($data, function ($data)
        {
            return [
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
                'about' => $this->transformer($data['about'], function ($model)
                {
                    return [
                        'id' => (int)$model['id'],
                        'title' => $model['title']
                    ];
                }),
                'parent' => $data['parent']
            ];
        });
    }
}