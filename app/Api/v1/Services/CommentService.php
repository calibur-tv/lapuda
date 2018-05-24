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
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

class CommentService extends Repository
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

    /**
     * TODO：comment 表加一个 comment_count 字段
     * TODO: create 和 delete 的时候，操作 comment_count 的 DB 和 Cache
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
        $parentId = isset($args['parent_id']) ? $args['parent_id'] : 0;
        $toUserId = isset($args['to_user_id']) ? $args['to_user_id'] : 0;
        $modalId = isset($args['modal_id']) ? $args['modal_id'] : 0;
        $now = Carbon::now();

        if (!$content || !$userId || ($parentId === 0 && $modalId === 0))
        {
            return null;
        }

        $id = DB::table($this->table)->insertGetId([
            'content' => $content,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'to_user_id' => $toUserId,
            'modal_id' => $modalId,
            'updated_at' => $now,
            'created_at' => $now
        ]);

        if ($parentId)
        {
            Redis::RPUSHX($this->getParentIdsKey($parentId), $id);
        }

        if ($modalId)
        {
            Redis::RPUSHX($this->getModalIdsKey($modalId), $id);
        }

        $job = (new \App\Jobs\Trial\Comment\Create($this->table, $id));
        dispatch($job);

        return $this->item($id, true);
    }

    public function list($ids)
    {
        if (empty($ids))
        {
            return [];
        }

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
        $comment = $this->item($id);

        if (is_null($comment))
        {
            return false;
        }

        if ($comment['from_user_id'] !== intval($userId))
        {
            return false;
        }

        DB::table($this->table)
            ->whereRaw('id = ? and user_id = ?', [$id, $userId])
            ->update([
                'state' => 4,
                'deleted_at' => Carbon::now()
            ]);

        if ($comment['parent_id'])
        {
            Redis::LREM($this->getParentIdsKey($comment['parent_id']), 1, $id);
        }

        if ($comment['modal_id'])
        {
            Redis::LREM($this->getModalIdsKey($comment['modal_id']), 1, $id);
        }

        return true;
    }

    public function getIdsByModalId($modalId, $maxId, $count = 10)
    {
        $ids = $this->RedisList($this->getModalIdsKey($modalId), function () use ($modalId)
        {
            return DB::table($this->table)
                ->where('modal_id', $modalId)
                ->orderBy('created_at', 'ASC')
                ->pluck('id');
        });

        return array_slice($ids, $maxId ? array_search($maxId, $ids) + 1 : 0, $count);
    }

    public function getIdsByParentId($parentId, $maxId = 0, $count = 10)
    {
        $ids = $this->RedisList($this->getParentIdsKey($parentId), function () use ($parentId)
        {
            return DB::table($this->table)
                ->where('parent_id', $parentId)
                ->orderBy('created_at', 'ASC')
                ->pluck('id');
        });

        return array_slice($ids, $maxId ? array_search($maxId, $ids) + 1 : 0, $count);
    }

    public function item($id, $force = false)
    {
        $result = $this->RedisHash($this->table . '_' . $id, function () use ($id, $force)
        {
            $tableName = $this->table;

            $comment = DB::table($tableName)
                ->where("$tableName.id", $id)
                ->when(!$force, function ($query) use ($tableName)
                {
                    return $query->where("$tableName.state", 1);
                })
                ->leftJoin('users AS from', 'from.id', '=', "$tableName.user_id")
                ->leftJoin('users AS to', 'to.id', '=', "$tableName.to_user_id")
                ->select(
                    "$tableName.id",
                    "$tableName.content",
                    "$tableName.modal_id",
                    "$tableName.parent_id",
                    "$tableName.created_at",
                    "$tableName.to_user_id",
                    "$tableName.user_id AS from_user_id",
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

            return json_decode(json_encode($comment), true);
        });

        if (is_null($result))
        {
            return null;
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

    protected function getParentIdsKey($parentId)
    {
        return $this->modal . '_' . $parentId . '_sub_comment_ids';
    }

    protected function getModalIdsKey($modalId)
    {
        return $this->modal . '_' . $modalId . '_comment_ids';
    }
}