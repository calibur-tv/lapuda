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
        parent::__construct('post_comments', 'ASC', true);
    }

    public function reply(array $args, $isMaster)
    {
        $comment = $this->create($args);

        if ($isMaster)
        {
            $postId = $args['modal_id'];
            $this->ListInsertAfter($this->postOnlySeeMasterIdsCacheKey($postId), $comment['id']);
        }

        return $comment;
    }

    public function deletePostComment($commentId, $userId, $postId, $isMaster)
    {
        DB::table($this->table)
            ->where('id', $commentId)
            ->update([
                'state' => 4,
                'deleted_at' => Carbon::now()
            ]);

        $this->ListRemove($this->getModalIdsKey($postId), $commentId);
        $this->ListRemove($this->userCommentCacheKey($userId), $commentId);
        if ($isMaster)
        {
            $this->ListRemove($this->postOnlySeeMasterIdsCacheKey($postId), $commentId);
        }
    }

    public function onlySeeMasterIds($postId, $masterId, $page = 0, $count = 10)
    {
        $ids = $this->RedisList($this->postOnlySeeMasterIdsCacheKey($postId), function () use ($postId, $masterId)
        {
            return DB::table($this->table)
                ->whereRaw('modal_id = ? and user_id = ?', [$postId, $masterId])
                ->orderBy('id', $this->order)
                ->pluck('id');
        });

        return $page === -1 ? $ids : array_slice($ids, $page * $count, $count);
    }

    protected function postOnlySeeMasterIdsCacheKey($postId)
    {
        return 'post_' . $postId . '_only_see_master_ids';
    }
}