<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/5/22
 * Time: 上午7:00
 */

namespace App\Api\V1\Services;

use App\Api\V1\Repositories\Repository;
use App\Api\V1\Transformers\CommentTransformer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

class Comment extends Repository
{
    /**
     * comment 的 state
     * 0：刚创建
     * 1：机器审核通过
     * 2：机器审核不通过需要人工审核
     * 3：机器审核不通过已删除
     * 4：用户自己删除
     * 5xxx：人工审核通过 xxx 是审核员 id
     * 6xxx：人工审核删除 xxx 是审核员 id
     * 7xxx：管理员删除 xxx 是管理员的 id
     */

    /**
     * comment 的 modal_id
     * 如果是 post 的 comment，
     * modal_id 代表的是哪一篇 post，
     * 就是 post_id
     */

    /**
     * comment 的 parent_id
     * 评论分为主评论和子评论，
     * 主评论有 modal_id，
     * 子评论没有 modal_id；
     * 主评论没有 parent_id，
     * 子评论有 parent_id
     * parent_id 就是主评论的 id
     */

    protected $modal;

    protected $table;

    protected $commentTransformer;

    public function __construct($modal)
    {
        $this->modal = $modal;

        $this->table = $modal . '_comments';
    }

    public function create(array $args)
    {
        $content =  Purifier::clean($args['content']);
        $userId = $args['user_id'];
        $parentId = $args['parent_id'] ?: 0;
        $toUserId = $args['toUserId'] ?: 0;
        $modalId = $args['modal_id'] ?: 0;

        if (!$content || !$userId || ($parentId === 0 && $modalId === 0))
        {
            return null;
        }

        $id = DB::table($this->table)->insertGetId([
            'content' => $content,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'to_user_id' => $toUserId,
            'modal_id' => $modalId
        ]);

        $job = (new \App\Jobs\Trial\Comment\Create($this->table, $id));
        dispatch($job);

        return $this->item($id, true);
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->item($id);
            if ($item) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function delete($id, $userId)
    {
        $comment = DB::table($this->table)
            ->whereRaw('id = ? and user_id = ?', [$id, $userId])
            ->count();

        if (!$comment)
        {
            return false;
        }

        DB::table($this->table)
            ->whereRaw('id = ? and user_id = ?', [$id, $userId])
            ->update([
                'state' => 4,
                'deleted_at' => Carbon::now()
            ]);

        return true;
    }

    protected function item($id, $force = false)
    {
        $comment = DB::table($this->table)
            ->where('comment.id', $id)
            ->when(!$force, function ($query)
            {
                return $query->where('comment.state', 1);
            })
            ->leftJoin('users AS from', 'from.id', '=', 'posts.user_id')
            ->leftJoin('users AS to', 'to.id', '=', 'posts.to_user_id')
            ->select(
                'comment.id',
                'comment.content',
                'comment.created_at',
                'comment.user_id AS from_user_id',
                'from.nickname AS from_user_name',
                'from.zone AS from_user_zone',
                'from.avatar AS from_user_avatar',
                'to.nickname AS to_user_name',
                'to.avatar AS to_user_avatar',
                'to.zone AS to_user_zone'
            )
            ->first();

        if (is_null($comment))
        {
            return null;
        }

        $result = $comment->toArray();

        if (!$force)
        {
            $this->RedisHash($this->table . '_' . $id, function () use ($result)
            {
                return $result;
            });
        }

        return $this->transformer()->item($result);
    }

    protected function transformer()
    {
        if (!$this->commentTransformer)
        {
            $this->commentTransformer = new CommentTransformer();
        }

        return $this->commentTransformer;
    }
}