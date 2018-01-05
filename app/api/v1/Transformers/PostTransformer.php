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
                'view_count' => (int)$post['view_count'],
                'title' => $post['title'],
                'desc' => $post['desc'],
                'images' => $post['images'],
                'created_at' => $post['created_at'],
                'updated_at' => $post['updated_at']
            ];
        });
    }

    public function reply($list)
    {
        return $this->collection($list, function ($post)
        {
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
                'comments' => $this->comments($post['comments']),
                'created_at' => $post['created_at']
            ];
        });
    }

    public function comments($list)
    {
        return $this->collection($list, function ($comment)
        {
            return [
                'id' => (int)$comment['id'],
                'content' => $comment['content'],
                'created_at' => $comment['created_at'],
                'from_user_id' => (int)$comment['from_user_id'],
                'from_user_name' => $comment['from_user_name'],
                'from_user_zone' => $comment['from_user_zone'],
                'from_user_avatar' => config('website.cdn').$comment['from_user_avatar'],
                'to_user_name' => $comment['to_user_name'] ? $comment['to_user_name'] : null,
                'to_user_zone' => $comment['to_user_zone'] ? $comment['to_user_zone'] : null,
            ];
        });
    }

    public function bangumi($list)
    {
        return $this->collection($list, function ($post)
        {
           return [
               'id' => (int)$post['id'],
               'title' => $post['title'],
               'desc' => $post['desc'],
               'images' => $post['images'],
               'created_at' => $post['created_at'],
               'updated_at' => $post['updated_at'],
               'view_count' => (int)$post['view_count'],
               'like_count' => (int)$post['like_count'],
               'comment_count' => (int)$post['comment_count'],
               'user' => $this->transformer($post['user'], function ($user)
               {
                   return [
                       'id' => (int)$user['id'],
                       'zone' => $user['zone'],
                       'avatar' => $user['avatar'],
                       'nickname' => $user['nickname']
                   ];
               })
           ];
        });
    }

    public function trending($list)
    {
        return $this->collection($list, function ($post)
        {
            return [
                'id' => (int)$post['id'],
                'title' => $post['title'],
                'desc' => $post['desc'],
                'images' => $post['images'],
                'created_at' => $post['created_at'],
                'updated_at' => $post['updated_at'],
                'view_count' => (int)$post['view_count'],
                'like_count' => (int)$post['like_count'],
                'comment_count' => (int)$post['comment_count'],
                'user' => $this->transformer($post['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'zone' => $user['zone'],
                        'nickname' => $user['nickname']
                    ];
                }),
                'bangumi' => $this->transformer($post['bangumi'], function ($bangumi)
                {
                   return [
                       'id' => (int)$bangumi['id'],
                       'name' => $bangumi['name'],
                       'avatar' => $bangumi['avatar']
                   ];
                })
            ];
        });
    }
}