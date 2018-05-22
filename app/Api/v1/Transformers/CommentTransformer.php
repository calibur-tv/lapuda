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
    public function item($comment)
    {
        return $this->transformer($comment, function ($comment)
        {
            return [
                'id' => (int)$comment['id'],
                'content' => $comment['content'],
                'created_at' => $comment['created_at'],
                'from_user_id' => (int)$comment['from_user_id'],
                'from_user_name' => $comment['from_user_name'],
                'from_user_zone' => $comment['from_user_zone'],
                'from_user_avatar' => config('website.image'). ($comment['from_user_avatar'] ?: 'default/user-avatar'),
                'to_user_name' => $comment['to_user_name'] ?: '',
                'to_user_zone' => $comment['to_user_zone'] ?: '',
                'to_user_avatar' => config('website.image'). ($comment['from_user_avatar'] ?: 'default/user-avatar'),
            ];
        });
    }
}