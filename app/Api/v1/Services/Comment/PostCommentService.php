<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/5/30
 * Time: 下午8:58
 */

namespace App\Api\V1\Services\Comment;


use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PostCommentService extends CommentService
{
    public function __construct()
    {
        parent::__construct('post_comments_v3', 'ASC', true);
    }

    public function deletePostComment($commentId, $userId, $postId)
    {
        DB::table($this->table)
            ->where('id', $commentId)
            ->update([
                'state' => 4,
                'deleted_at' => Carbon::now()
            ]);

        $this->ListRemove($this->getModalIdsKey($postId), $commentId);
        $this->ListRemove($this->userCommentCacheKey($userId), $commentId);
    }
}