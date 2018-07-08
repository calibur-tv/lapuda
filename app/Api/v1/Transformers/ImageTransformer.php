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

    public function cartoon($list)
    {
        return $this->collection($list, function ($image)
        {
            return [
                'id' => (int)$image['id'],
                'width' => (int)$image['width'],
                'height' => (int)$image['height'],
                'url' => $image['url'],
                'name' => $image['name'],
                'album_id' => (int)$image['album_id'],
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
                'created_at' => $image['created_at']
            ];
        });
    }

    public function roleShow($list)
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

    public function albumShow($data)
    {
         return $this->transformer($data, function ($album)
         {
            return [
                'user' => $album['user'],
                'bangumi' => $album['bangumi'],
                'images' => $this->collection($album['images'], function ($image)
                 {
                     return [
                         'id' => (int)$image['id'],
                         'url' => $image['url'],
                         'width' => (int)$image['width'],
                         'height' => (int)$image['height']
                     ];
                 }),
                'info' => $this->transformer($album['info'], function ($info)
                {
                    return [
                        'liked' => $info['liked'],
                        'name' => $info['name'],
                        'poster' => $info['url'],
                        'images' => $info['images'],
                        'is_cartoon' => (boolean)$info['is_cartoon'],
                        'is_creator' => (boolean)$info['creator'],
                        'like_count' => (int)$info['like_count'],
                        'image_count' => (int)$info['image_count'],
                        'view_count' => (int)$info['view_count'],
                        'like_users' => $info['like_users'],
                        'created_at' => $info['created_at'],
                        'updated_at' => $info['updated_at']
                    ];
                }),
                'cartoon' => $album['cartoon']
            ];
         });
    }

    public function indexBanner($list)
    {
        return $this->collection($list, function ($data)
        {
            return array_key_exists('deleted_at', $data) ? [
                'id' => (int)$data['id'],
                'url' => $data['url'],
                'gray' => (int)$data['gray'],
                'user_id' => (int)$data['user_id'],
                'user_nickname' => $data['user_nickname'],
                'user_zone' => $data['user_zone'],
                'user_avatar' => $data['user_avatar'],
                'bangumi_id' => (int)$data['bangumi_id'],
                'bangumi_name' => $data['bangumi_name'],
                'use' => !$data['deleted_at']
            ] : [
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

    public function choiceUserAlbum($list)
    {
        return $this->collection($list, function ($album)
        {
            return [
                'id' => $album['id'],
                'bangumi_id' => $album['bangumi_id'],
                'name' => $album['name'],
                'url' => $album['url']
            ];
        });
    }
}