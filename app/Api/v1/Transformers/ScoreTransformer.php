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
            'bangumi' => $score['bangumi'],
            'total' => (int)$score['total'] / 10,
            'lol' => (int)$score['lol'] / 2,
            'cry' => (int)$score['cry'] / 2,
            'fight' => (int)$score['fight'] / 2,
            'moe' => (int)$score['moe'] / 2,
            'sound' => (int)$score['sound'] / 2,
            'vision' => (int)$score['vision'] / 2,
            'role' => (int)$score['role'] / 2,
            'story' => (int)$score['story'] / 2,
            'express' => (int)$score['express'] / 2,
            'style' => (int)$score['style'] / 2,
            'intro' => $score['intro'],
            'content' => $score['content'],
            'created_at' => $score['created_at'],
            'commented' => (int)$score['commented'],
            'comment_count' => (int)$score['comment_count'],
            'liked' => (int)$score['liked'],
            'like_count' => (int)$score['like_count'],
            'like_users' => $score['like_users']
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
                'like_count' => (int)$score['like_count'],
                'comment_count' => (int)$score['comment_count'],
                'intro' => $score['intro'],
                'total' => (int)$score['total'],
                'created_at' => $score['created_at']
            ];
        });
    }
}