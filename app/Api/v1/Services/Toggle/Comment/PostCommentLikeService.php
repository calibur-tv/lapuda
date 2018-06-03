<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午4:57
 */

namespace App\Api\V1\Services\Toggle\Comment;


class PostCommentLikeService extends CommentLikeService
{
    public function __construct()
    {
        parent::__construct('post_comments_v3', 'post_comment_like');
    }
}