<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/5/30
 * Time: 下午8:58
 */

namespace App\Api\V1\Services\Comment;


use App\Api\V1\Services\Counter\Post\PostReplyCounter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PostCommentService extends CommentService
{
    public function __construct()
    {
        parent::__construct('post_comments', 'ASC', true, true);
    }
}