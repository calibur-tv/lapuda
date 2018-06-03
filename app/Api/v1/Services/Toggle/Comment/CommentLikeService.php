<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午4:55
 */

namespace App\Api\V1\Services\Toggle\Comment;


use App\Api\V1\Services\Toggle\ToggleService;

class CommentLikeService extends ToggleService
{
    public function __construct($modalTable, $toggleTable)
    {
        parent::__construct($modalTable, 'comment_count', $toggleTable);
    }
}