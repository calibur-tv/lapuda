<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午9:21
 */

namespace App\Api\V1\Transformers;


class ImageTransformer extends Transformer
{
    public function indexBanner($list)
    {
        return $this->collection($list, function ($data)
        {
            return [
                'id' => (int)$data['id'],
                'user_id' => (int)$data['user_id'],
                'bangumi_id' => (int)$data['bangumi_id'],
                'url' => $data['url'],
                'gray' => (int)$data['gray'],
                'user' => isset($data['user']) ? $this->transformer($data['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'nickname' => $user['nickname'],
                        'zone' => $user['zone'],
                        'avatar' => $user['avatar']
                    ];
                }) : null,
                'bangumi' => isset($data['bangumi']) ? $this->transformer($data['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name']
                    ];
                }) : null
            ];
        });
    }
}