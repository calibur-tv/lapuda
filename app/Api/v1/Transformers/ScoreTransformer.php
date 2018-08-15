<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/12
 * Time: 上午7:35
 */

namespace App\Api\V1\Transformers;


class ScoreTransformer extends Transformer
{
    public function show($score)
    {
        return [
            'id' => (int)$score['id'],
            'user' => [
                'id' => (int)$score['user']['id'],
                'zone' => $score['user']['zone'],
                'nickname' => $score['user']['nickname'],
                'avatar' => $score['user']['avatar']
            ],
            'title' => $score['title'],
            'bangumi' => $score['bangumi'],
            'total' => $score['total'],
            'lol' => $score['lol'],
            'cry' => $score['cry'],
            'fight' => $score['fight'],
            'moe' => $score['moe'],
            'sound' => $score['sound'],
            'vision' => $score['vision'],
            'role' => $score['role'],
            'story' => $score['story'],
            'express' => $score['express'],
            'style' => $score['style'],
            'intro' => $score['intro'],
            'content' => $score['content'],
            'is_creator' => (boolean)$score['is_creator'],
            'liked' => $score['liked'],
            'like_users' => $score['like_users'],
            'rewarded' => $score['rewarded'],
            'reward_users' => $score['reward_users'],
            'marked' => $score['marked'],
            'mark_users' => $score['mark_users'],
            'created_at' => $score['created_at'],
            'updated_at' => $score['updated_at'],
            'published_at' => $score['published_at']
        ];
    }

    public function users($list)
    {
        return $this->collection($list, function ($score)
        {
            return [
                'id' => (int)$score['id'],
                'bangumi' => $this->transformer($score['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                }),
                'title' => $score['title'],
                'like_count' => (int)$score['like_count'],
                'comment_count' => (int)$score['comment_count'],
                'intro' => $score['intro'],
                'total' => (int)$score['total'],
                'created_at' => $score['created_at']
            ];
        });
    }

    public function drafts($list)
    {
        return $this->collection($list, function ($score)
        {
            return [
                'id' => (int)$score['id'],
                'bangumi' => $score['bangumi'],
                'title' => $score['title'],
                'intro' => $score['intro']
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
                'title' => $item['title'],
                'intro' => $item['intro'],
                'total' => (int)$item['total'],
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