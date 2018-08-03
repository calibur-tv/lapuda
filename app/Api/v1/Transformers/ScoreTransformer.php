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
            'commented' => (int)$score['commented'],
            'comment_count' => (int)$score['comment_count'],
            'liked' => $score['liked'],
            'like_count' => (int)$score['like_count'],
            'like_users' => $score['like_users'],
            'rewarded' => $score['rewarded'],
            'reward_count' => (int)$score['reward_count'],
            'reward_users' => $score['reward_users'],
            'marked' => $score['marked'],
            'mark_count' => (int)$score['mark_count'],
            'created_at' => $score['created_at'],
            'updated_at' => $score['updated_at'],
            'published_at' => $score['published_at']
        ];
    }

    public function trending($list)
    {
        return $this->collection($list, function ($score)
        {
            return [
                'id' => (int)$score['id'],
                'user' => $this->transformer($score['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'zone' => $user['zone'],
                        'nickname' => $user['nickname'],
                        'avatar' => $user['avatar']
                    ];
                }),
                'bangumi' => $this->transformer($score['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                }),
                'title' => $score['title'],
                'liked' => (boolean)$score['liked'],
                'commented' => (boolean)$score['commented'],
                'like_count' => (int)$score['like_count'],
                'comment_count' => (int)$score['comment_count'],
                'intro' => $score['intro'],
                'total' => (int)$score['total'],
                'created_at' => $score['created_at']
            ];
        });
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
}