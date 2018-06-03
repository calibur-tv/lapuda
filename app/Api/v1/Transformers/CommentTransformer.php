<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/4
 * Time: 下午9:17
 */

namespace App\Api\V1\Transformers;


class CommentTransformer extends Transformer
{
    public function sub($comment)
    {
        return $this->transformer($comment, function ($comment)
        {
            return [
                'id' => (int)$comment['id'],
                'content' => $comment['content'],
                'created_at' => $comment['created_at'],
                'parent_id' => (int)$comment['parent_id'],
                'comment_count' => (int)$comment['comment_count'],
                'from_user_id' => (int)$comment['from_user_id'],
                'from_user_name' => $comment['from_user_name'],
                'from_user_zone' => $comment['from_user_zone'],
                'from_user_avatar' => config('website.image'). ($comment['from_user_avatar'] ? $comment['from_user_avatar'] : 'default/user-avatar'),
                'to_user_id' => (int)$comment['to_user_id'],
                'to_user_name' => $comment['to_user_name'] ?: '',
                'to_user_zone' => $comment['to_user_zone'] ?: '',
                'to_user_avatar' => config('website.image'). ($comment['to_user_avatar'] ? $comment['to_user_avatar'] : 'default/user-avatar'),
            ];
        });
    }

    public function main($comment)
    {
        return $this->transformer($comment, function ($comment)
        {
            return [
                'id' => (int)$comment['id'],
                'content' => $comment['content'],
                'images' => $this->collection($comment['images'], function ($image)
                {
                    return [
                        'url' =>  config('website.image'). $image['key'],
                        'width' => (int)$image['width'],
                        'height' => (int)$image['height'],
                        'size' => (int)$image['size'],
                        'type' => $image['type']
                    ];
                }),
                'created_at' => $comment['created_at'],
                'modal_id' => (int)$comment['modal_id'],
                'comment_count' => (int)$comment['comment_count'],
                'floor_count' => isset($comment['floor_count']) ? (int)$comment['floor_count'] : 0,
                'from_user_id' => (int)$comment['from_user_id'],
                'from_user_name' => $comment['from_user_name'],
                'from_user_zone' => $comment['from_user_zone'],
                'from_user_avatar' => config('website.image'). ($comment['from_user_avatar'] ? $comment['from_user_avatar'] : 'default/user-avatar')
            ];
        });
    }
}