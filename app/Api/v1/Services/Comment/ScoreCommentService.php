<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午4:06
 */

namespace App\Api\V1\Services\Comment;


class ScoreCommentService extends CommentService
{
    public function __construct()
    {
        parent::__construct('score', 'DESC');
    }
}