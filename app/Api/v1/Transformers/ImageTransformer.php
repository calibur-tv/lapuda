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
    public function show($data)
    {
        return $this->transformer($data, function ($image)
        {
            return [
                'id' => (int)$image['id'],
                'user_id' => (int)$image['user_id'],
                'bangumi_id' => (int)$image['bangumi_id'],
                'name' => $image['name'] ? $image['name'] : '未命名',
                'part' => (int)$image['part'],
                'parts' => $image['parts'],
                'images' => $image['images'],
                'view_count' => (int)$image['view_count'],
                'image_count' => (int)$image['image_count'],
                'user' => $this->transformer($image['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'zone' => $user['zone'],
                        'avatar' => $user['avatar'],
                        'nickname' => $user['nickname']
                    ];
                }),
                'bangumi' => $image['bangumi'],
                'source' => [
                    'url' => $image['url'],
                    'width' => (int)$image['width'],
                    'height' => (int)$image['height'],
                    'size' => (int)$image['size'],
                    'type' => $image['type']
                ],
                'is_album' => (boolean)$image['is_album'],
                'is_cartoon' => (boolean)$image['is_cartoon'],
                'is_creator' => (boolean)$image['is_creator'],
                'liked' => $image['liked'],
                'marked' => $image['marked'],
                'rewarded' => $image['rewarded'],
                'like_users' => $image['like_users'],
                'reward_users' => $image['reward_users'],
                'mark_users' => $image['mark_users'],
                'created_at' => $image['created_at'],
                'updated_at' => $image['updated_at']
            ];
        });
    }

    public function userAlbums($list)
    {
        return $this->collection($list, function ($album)
        {
            return [
                'id' => (int)$album['id'],
                'name' => $album['name'],
                'is_cartoon' => $album['is_cartoon'],
                'is_creator' => $album['is_creator'],
                'image_count' => $album['image_count'],
                'poster' => $album['url']
            ];
        });
    }

    public function cartoon($list)
    {
        return $this->collection($list, function ($image)
        {
            return [
                'id' => (int)$image['id'],
                'user_id' => (int)$image['user_id'],
                'bangumi_id' => (int)$image['bangumi_id'],
                'name' => $image['name'] ? $image['name'] : '未命名',
                'part' => (int)$image['part'],
                'image_count' => (int)$image['image_count'],
                'user' => $this->transformer($image['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'nickname' => $user['nickname'],
                        'zone' => $user['zone'],
                        'avatar' => $user['avatar']
                    ];
                }),
                'source' => [
                    'url' => $image['url'],
                    'width' => (int)$image['width'],
                    'height' => (int)$image['height'],
                    'size' => (int)$image['size'],
                    'type' => $image['type']
                ],
                'is_creator' => (boolean)$image['is_creator'],
                'created_at' => $image['created_at'],
                'updated_at' => $image['updated_at']
            ];
        });
    }

    public function albumImages($images)
    {
        return $this->collection($images, function ($image)
        {
            return [
                'id' => $image['id'],
                'url' => $image['url'],
                'width' => (int)$image['width'],
                'height' => (int)$image['height'],
                'size' => (int)$image['size'],
                'type' => $image['type']
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

    public function search()
    {
        return null;
    }

    public function userFlow($list)
    {
        return $this->collection($list, function ($item)
        {
            return array_merge(
                $this->baseFlow($item),
                [
                    'bangumi' => $this->transformer($item['bangumi'], function ($bangumi)
                    {
                        return [
                            'id' => (int)$bangumi['id'],
                            'name' => $bangumi['name'],
                            'avatar' => $bangumi['avatar']
                        ];
                    })
                ]
            );
        });
    }

    public function bangumiFlow($list)
    {
        return $this->collection($list, function ($item)
        {
            return array_merge(
                $this->baseFlow($item),
                [
                    'user' => $this->transformer($item['user'], function ($user)
                    {
                        return [
                            'id' => (int)$user['id'],
                            'nickname' => $user['nickname'],
                            'avatar' => $user['avatar'],
                            'zone' => $user['zone']
                        ];
                    })
                ]
            );
        });
    }

    public function trendingFlow($list)
    {
        return $this->collection($list, function ($item)
        {
            return array_merge(
                $this->baseFlow($item),
                [
                    'bangumi' => $this->transformer($item['bangumi'], function ($bangumi)
                    {
                        return [
                            'id' => (int)$bangumi['id'],
                            'name' => $bangumi['name'],
                            'avatar' => $bangumi['avatar']
                        ];
                    }),
                    'user' => $this->transformer($item['user'], function ($user)
                    {
                        return [
                            'id' => (int)$user['id'],
                            'nickname' => $user['nickname'],
                            'avatar' => $user['avatar'],
                            'zone' => $user['zone']
                        ];
                    })
                ]
            );
        });
    }

    protected function baseFlow($item)
    {
        return $this->transformer($item, function ($item)
        {
            return [
                'id' => (int)$item['id'],
                //
                'name' => $item['name'] ? $item['name'] : '未命名',
                'image_count' => (int)$item['image_count'],
                'source' => [
                    'url' => $item['url'],
                    'width' => (int)$item['width'],
                    'height' => (int)$item['height'],
                    'size' => (int)$item['size'],
                    'type' => $item['type']
                ],
                'is_album' => (boolean)$item['is_album'],
                //
                'is_creator' => (boolean)$item['is_creator'],
                'like_count' => $item['like_count'],
                'reward_count' => $item['reward_count'],
                'comment_count' => $item['comment_count'],
                'mark_count' => $item['mark_count'],
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at']
            ];
        });
    }
}