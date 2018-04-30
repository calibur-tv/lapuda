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
    public function waterfall($list)
    {
        return $this->collection($list, function ($image)
        {
            return [
                'id' => (int)$image['id'],
                'width' => (int)$image['width'],
                'height' => (int)$image['height'],
                'url' => $image['url'],
                'name' => $image['name'] ?: '',
                'user_id' => (int)$image['user_id'],
                'album_id' => (int)$image['album_id'],
                'bangumi_id' => (int)$image['bangumi_id'],
                'role_id' => (int)$image['role_id'],
                'size' => $image['size'],
                'tags' => $image['tags'],
                'creator' => (boolean)$image['creator'],
                'is_cartoon' => (boolean)$image['is_cartoon'],
                'liked' => (boolean)$image['liked'],
                'like_count' => (int)$image['like_count'],
                'image_count' => (int)$image['image_count'],
                'user' => $image['user'] ? $this->transformer($image['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'zone' => $user['zone'],
                        'nickname' => $user['nickname'],
                        'avatar' => $user['avatar']
                    ];
                }) : null,
                'role' => $image['role'] ? $this->transformer($image['role'], function ($role)
                {
                    return [
                        'id' => (int)$role['id'],
                        'name' => $role['name']
                    ];
                }) : null,
                'bangumi' => $image['bangumi_id'] ? $this->transformer($image['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                }) : null,
                'created_at' => $image['created_at']
            ];
        });
    }

    public function albumShow($images)
    {
     return $this->collection($images, function ($image)
     {
        return [
            'id' => (int)$image['id'],
            'url' => $image['width'] . '-' . $image['height'] . '|' . $image['url']
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

    public function albums($list)
    {
        return $this->collection($list, function ($item)
        {
            return [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'image_count' => (int)$item['image_count'],
                'bangumi_id' => (int)$item['bangumi_id'],
                'is_cartoon' => (boolean)$item['is_cartoon'],
                'url' => $item['url'],
                'width' => (int)$item['width'],
                'height' => (int)$item['height'],
            ];
        });
    }
}