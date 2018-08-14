<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/4
 * Time: 下午9:17
 */

namespace App\Api\V1\Transformers;


class PostTransformer extends Transformer
{
    public function show($post)
    {
        return $this->transformer($post, function ($post)
        {
            return [
                'id' => (int)$post['id'],
                'comment_count' => (int)$post['comment_count'],
                'like_count' => (int)$post['like_count'],
                'reward_count' => (int)$post['reward_count'],
                'view_count' => (int)$post['view_count'],
                'mark_count' => (int)$post['mark_count'],
                'title' => $post['title'],
                'desc' => $post['desc'],
                'liked' => $post['liked'],
                'rewarded' => $post['rewarded'],
                'marked' => $post['marked'],
                'commented' => $post['commented'],
                'content' => $post['content'],
                'images' => $this->collection($post['images'], function ($image)
                {
                    return [
                        'width' => (int)$image['width'],
                        'height' => (int)$image['height'],
                        'size' => (int)$image['size'],
                        'type' => $image['type'],
                        'url' => config('website.image'). $image['url']
                    ];
                }),
                'preview_images' => $this->collection($post['preview_images'], function ($image)
                {
                    return [
                        'width' => (int)$image['width'],
                        'height' => (int)$image['height'],
                        'size' => (int)$image['size'],
                        'type' => $image['type'],
                        'url' => config('website.image') . $image['url']
                    ];
                }),
                'like_users' => $post['like_users'],
                'reward_users' => $post['reward_users'],
                'is_nice' => (boolean)$post['is_nice'],
                'is_creator' => (boolean)$post['is_creator'],
                'created_at' => $post['created_at'],
                'updated_at' => $post['updated_at']
            ];
        });
    }

    public function userReply($comment)
    {
        return $this->transformer($comment, function ($comment)
        {
            return [
                'id' => (int)$comment['id'],
                'content' => $comment['content'],
                'images' => $comment['images'],
                'created_at' => $comment['created_at'],
                'floor_count' => (int)$comment['floor_count'],
                'bangumi' => $this->transformer($comment['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                }),
                'post' => $this->transformer($comment['post'], function ($post)
                {
                    return [
                        'id' => (int)$post['id'],
                        'title' => $post['title'],
                        'content' => $post['content'],
                        'images' => $post['images']
                    ];
                })
            ];
        });
    }

    public function search($post)
    {
        return $this->transformer($post, function ($post)
        {
            return [
                'id' => (int)$post['id'],
                'title' => $post['title'],
                'desc' => $post['desc'],
                'images' => $post['images'],
                'created_at' => $post['created_at']
            ];
        });
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
                'title' => $item['title'] ? $item['title'] : '标题什么的不重要',
                'desc' => $item['desc'],
                'images' => $this->collection($item['images'], function ($image)
                {
                    return [
                        'width' => (int)$image['width'],
                        'height' => (int)$image['height'],
                        'size' => (int)$image['size'],
                        'type' => $image['type'],
                        'url' => config('website.image'). $image['url']
                    ];
                }),
                'top_at' => $item['top_at'],
                'is_nice' => (boolean)$item['is_nice'],
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