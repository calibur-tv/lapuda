<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/4
 * Time: 下午9:17
 */

namespace App\Api\V1\Transformers;


class PostTransformer
{
    public function item($post)
    {
        return $this->list([$post])[0];
    }

    public function show($post)
    {
        return fractal([$post], function (array $post) {
            return [
                'id' => (int)$post['id'],
                'bangumi_id' => (int)$post['bangumi_id'],
                'comment_count' => (int)$post['comment_count'],
                'like_count' => (int)$post['like_count'],
                'view_count' => (int)$post['view_count'],
                'title' => $post['title'],
                'desc' => $post['desc'],
                'content' => $post['content'],
                'images' => $post['images'],
                'user' => [
                    'id' => (int)$post['user']['id'],
                    'zone' => $post['user']['zone'],
                    'avatar' => $post['user']['avatar'],
                    'nickname' => $post['user']['nickname']
                ],
                'created_at' => $post['created_at'],
                'updated_at' => $post['updated_at']
            ];
        })->toArray()['data'][0];
    }

    public function list($list)
    {
        return fractal($list, function (array $post) {
            return [
                'id' => (int)$post['id'],
                'comment_count' => (int)$post['comment_count'],
                'like_count' => (int)$post['like_count'],
                'content' => $post['content'],
                'images' => $post['images'],
                'user' => [
                    'id' => (int)$post['user']['id'],
                    'zone' => $post['user']['zone'],
                    'avatar' => $post['user']['avatar'],
                    'nickname' => $post['user']['nickname']
                ],
                'comments' => fractal($post['comments'], function (array $comment) {
                    return [
                        'id' => (int)$comment['id'],
                        'content' => $comment['content'],
                        'created_at' => $comment['created_at'],
                        'from_user_id' => (int)$comment['from_user_id'],
                        'from_user_name' => $comment['from_user_name'],
                        'from_user_zone' => $comment['from_user_zone'],
                        'from_user_avatar' => config('website.cdn').$comment['from_user_avatar'],
                        'to_user_name' => $comment['to_user_name'],
                        'to_user_zone' => $comment['to_user_zone'],
                    ];
                })->toArray()['data'],
                'created_at' => $post['created_at']
            ];
        })->toArray()['data'];
    }
}