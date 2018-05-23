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
            $userTransformer = new UserTransformer();

            return [
                'id' => (int)$post['id'],
                'comment_count' => (int)$post['comment_count'],
                'like_count' => (int)$post['like_count'],
                'view_count' => (int)$post['view_count'],
                'mark_count' => (int)$post['mark_count'],
                'title' => $post['title'],
                'desc' => $post['desc'],
                'liked' => $post['liked'],
                'marked' => $post['marked'],
                'commented' => $post['commented'],
                'content' => $post['content'],
                'images' => $post['images'],
                'created_at' => $post['created_at'],
                'updated_at' => $post['updated_at'],
                'previewImages' => $post['previewImages'],
                'floor_count' => (int)$post['floor_count'],
                'likeUsers' => $userTransformer->list($post['likeUsers']),
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
                'floor_count' => (int)$post['floor_count'],
                'liked' => $post['liked'],
                'user' => [
                    'id' => (int)$post['user']['id'],
                    'zone' => $post['user']['zone'],
                    'avatar' => $post['user']['avatar'],
                    'nickname' => $post['user']['nickname']
                ],
                'comments' => $post['comments'],
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
                'from_user_avatar' => config('website.image'). ($comment['from_user_avatar'] ? $comment['from_user_avatar'] : 'default/user-avatar'),
                'to_user_name' => $comment['to_user_name'] ? $comment['to_user_name'] : null,
                'to_user_zone' => $comment['to_user_zone'] ? $comment['to_user_zone'] : null
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
               'mark_count' => (int)$post['mark_count'],
               'user' => $this->transformer($post['user'], function ($user)
               {
                   return [
                       'id' => (int)$user['id'],
                       'zone' => $user['zone'],
                       'avatar' => $user['avatar'],
                       'nickname' => $user['nickname']
                   ];
               }),
               'previewImages' => $post['previewImages'],
               'liked' => $post['liked'],
               'marked' => $post['marked'],
               'commented' => $post['commented']
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
                'mark_count' => (int)$post['mark_count'],
                'user' => $this->transformer($post['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'zone' => $user['zone'],
                        'nickname' => $user['nickname'],
                        'avatar' => $user['avatar']
                    ];
                }),
                'bangumi' => $this->transformer($post['bangumi'], function ($bangumi)
                {
                   return [
                       'id' => (int)$bangumi['id'],
                       'name' => $bangumi['name'],
                       'avatar' => $bangumi['avatar']
                   ];
                }),
                'previewImages' => $post['previewImages'],
                'liked' => $post['liked'],
                'marked' => $post['marked'],
                'commented' => $post['commented']
            ];
        });
    }

    public function usersMine($list)
    {
        return $this->collection($list, function ($post)
        {
            return [
                'id' => (int)$post['id'],
                'title' => $post['title'],
                'desc' => $post['desc'],
                'images' => $post['images'],
                'created_at' => $post['created_at'],
                'view_count' => (int)$post['view_count'],
                'like_count' => (int)$post['like_count'],
                'comment_count' => (int)$post['comment_count'],
                'previewImages' => $post['previewImages'],
                'bangumi' => $this->transformer($post['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                }),
            ];
        });
    }

    public function userReply($post)
    {
        return $this->transformer($post, function ($post)
        {
            return [
                'id' => (int)$post['id'],
                'content' => $post['content'],
                'images' => $post['images'],
                'created_at' => $post['created_at'],
                'bangumi' => $this->transformer($post['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name']
                    ];
                }),
                'user' => $this->transformer($post['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'zone' => $user['zone'],
                        'nickname' => $user['nickname']
                    ];
                }),
                'parent' => $this->transformer($post['parent'], function ($parent)
                {
                    return [
                        'id' => (int)$parent['id'],
                        'content' => $parent['content'],
                        'images' => $parent['images'],
                        'floor_count' => (int)$parent['floor_count']
                    ];
                }),
                'post' => $this->transformer($post['post'], function ($main)
                {
                    return [
                        'id' => (int)$main['id'],
                        'title' => $main['title']
                    ];
                })
            ];
        });
    }

    public function userMark($list)
    {
        return $this->collection($list, function ($post)
        {
            return [
                'id' => (int)$post['id'],
                'title' => $post['title']
            ];
        });
    }

    public function userLike($list)
    {
        $posts = [];
        foreach ($list as $item)
        {
            if ($item['title'])
            {
                $posts[] = $item;
            }
        }
        return $this->collection($posts, function ($post)
        {
            return [
                'id' => (int)$post['id'],
                'title' => $post['title']
            ];
        });
    }
}