<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/18
 * Time: 下午8:51
 */

namespace App\Api\V1\Services\Comment;


class ImageCommentService extends CommentService
{
    public function __construct()
    {
        parent::__construct('image', 'DESC');
    }
}