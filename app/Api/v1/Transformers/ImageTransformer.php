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
    public function userList($list)
    {
        return $this->collection($list, function ($image)
        {
            return [
                'id' => (int)$image['id'],
                'width' => (int)$image['width'],
                'height' => (int)$image['height'],
                'url' => $image['url'],
                'name' => $image['name'],
                'tags' => $image['tags'],
                'creator' => (boolean)$image['creator'],
                'bangumi' => $this->transformer($image['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                }),
                'role' => $image['role'] ? $this->transformer($image['role'], function ($role)
                {
                    return [
                        'id' => (int)$role['id'],
                        'name' => $role['name'],
                        'avatar' => $role['avatar']
                    ];
                }) : null
            ];
        });
    }

    public function indexBanner($list)
    {
        return $this->collection($list, function ($data)
        {
            return [
                'id' => (int)$data['id'],
                'url' => $data['url'],
                'gray' => (int)$data['gray'],
                'user_id' => (int)$data['user_id'],
                'user_nickname' => $data['user_nickname'],
                'user_zone' => $data['user_zone'],
                'user_avatar' => $data['user_avatar'],
                'bangumi_id' => (int)$data['bangumi_id'],
                'bangumi_name' => $data['bangumi_name']
            ];
        });
    }
}