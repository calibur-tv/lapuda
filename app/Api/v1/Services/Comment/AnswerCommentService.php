<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 下午2:21
 */

namespace App\Api\V1\Services\Comment;


class AnswerCommentService extends CommentService
{
    public function __construct()
    {
        parent::__construct('answer', 'DESC');
    }
}