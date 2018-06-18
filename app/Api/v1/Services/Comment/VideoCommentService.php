<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/18
 * Time: 下午8:50
 */

namespace App\Api\V1\Services\Comment;


class VideoCommentService extends CommentService
{
    public function __construct()
    {
        parent::__construct('video', 'DESC');
    }
}