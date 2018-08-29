<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/30
 * Time: 上午7:19
 */

namespace App\Api\V1\Services\Comment;


class CartoonRoleCommentService extends CommentService
{
    public function __construct()
    {
        parent::__construct('cartoon_role', 'DESC');
    }
}