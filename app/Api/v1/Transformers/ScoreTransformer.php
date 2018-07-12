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
                'intro' => $score['intro'],
                'total' => (int)$score['total'],
                'created_at' => $score['created_at']
            ];
        });
    }
}