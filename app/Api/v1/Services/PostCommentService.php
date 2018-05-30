<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/5/30
 * Time: 下午8:58
 */

namespace App\Api\V1\Services;


class PostCommentService extends CommentService
{
    public function __construct()
    {
        parent::__construct('post');
    }
}