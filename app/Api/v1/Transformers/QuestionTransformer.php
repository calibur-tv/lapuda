<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/4
 * Time: 下午9:17
 */

namespace App\Api\V1\Transformers;


class QuestionTransformer extends Transformer
{
    public function show($question)
    {
        return $this->transformer($question, function ($question)
        {
            return [
                'id' => (int)$question['id'],
                'title' => $question['title'],
                'intro' => $question['intro'],
                'content' => $question['content'],
                'images' => $this->collection($question['images'], function ($image)
                {
                    return [
                        'width' => (int)$image['width'],
                        'height' => (int)$image['height'],
                        'size' => (int)$image['size'],
                        'type' => $image['type'],
                        'url' => config('website.image'). $image['url']
                    ];
                }),
                'tags' => $question['tags'],
                'user_id' => (int)$question['user_id'],
                'view_count' => (int)$question['view_count'],
                'commented' => $question['commented'],
                'answer_count' => $question['answer_count'],
                'comment_count' => (int)$question['comment_count'],
                'followed' => $question['followed'],
                'follow_users' => $question['follow_users'],
                'created_at' => $question['created_at'],
                'updated_at' => $question['updated_at']
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