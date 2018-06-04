<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/5/22
 * Time: 上午7:00
 */

namespace App\Api\V1\Services\Comment;

use App\Api\V1\Repositories\Repository;
use App\Api\V1\Services\Counter\CounterService;
use App\Api\V1\Transformers\CommentTransformer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

class CommentService extends Repository
{
    /**
     * comment 的 state
     * 0：刚创建
     * 1：机器或人工审核通过
     * 2：机器审核不通过需要人工审核
     * 3：机器审核不通过已删除
     * 4：用户自己删除
     * 5xxx：人工审核删除 xxx 是审核员 id
     * 6xxx：管理员删除 xxx 是管理员的 id
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
     * 主评论只有 modal_id，
     * 子评论只有 parent_id
     * parent_id 就是主评论的 id
     */

    protected $table;

    protected $order;

    protected $floor_count;

    protected $commentTransformer;

    public function __construct($table, $order = 'ASC', $floor_count = false)
    {
        $this->table = $table;

        $this->order = $order === 'ASC' ? 'ASC' : 'DESC';

        $this->floor_count = $floor_count;
    }

    public function create(array $args)
    {
        $content = Purifier::clean($args['content']);
        $content = gettype($content) === 'array' ? json_encode($content) : $content;
        $userId = $args['user_id'];
        $parentId = isset($args['parent_id']) ? $args['parent_id'] : 0;
        $toUserId = isset($args['to_user_id']) ? $args['to_user_id'] : 0;
        $modalId = isset($args['modal_id']) ? $args['modal_id'] : 0;
        $now = Carbon::now();

        if (!$content || !$userId || ($parentId === 0 && $modalId === 0))
        {
            return null;
        }

        if ($modalId && $this->floor_count)
        {
            $count = DB::table($this->table)
                ->where('modal_id', $modalId)
                ->count();

            $id = DB::table($this->table)->insertGetId([
                'content' => $content,
                'user_id' => $userId,
                'modal_id' => $modalId,
                'floor_count' => $count + 2,
                'updated_at' => $now,
                'created_at' => $now
            ]);
        }
        else
        {
            $id = DB::table($this->table)->insertGetId([
                'content' => $content,
                'user_id' => $userId,
                'parent_id' => $parentId,
                'to_user_id' => $toUserId,
                'modal_id' => $modalId,
                'updated_at' => $now,
                'created_at' => $now
            ]);
        }

        if ($parentId)
        {
            if ($this->order === 'ASC') {
                $this->ListInsertAfter($this->getParentIdsKey($parentId), $id);
            } else {
                $this->ListInsertBefore($this->getParentIdsKey($parentId), $id);
            }
            // 更改主评论的回复数
            $this->writeCommentCount($parentId, true);

            $job = (new \App\Jobs\Trial\Comment\CreateSubComment($this->table, $id));
            dispatch($job);

            return $this->getSubCommentItem($id, true);
        }

        if ($modalId)
        {
            if ($this->order === 'ASC') {
                $this->ListInsertAfter($this->getModalIdsKey($modalId), $id);
            } else {
                $this->ListInsertBefore($this->getModalIdsKey($modalId), $id);
            }
            // 更新用户的回复列表
            $this->ListInsertBefore($this->userCommentCacheKey($userId), $id);

            $job = (new \App\Jobs\Trial\Comment\CreateMainComment($this->table, $id));
            dispatch($job);

            return $this->getMainCommentItem($id, true);
        }

        return null;
    }

    public function update($id, array $data)
    {
        DB::table($this->table)
            ->where('id', $id)
            ->update(array_merge($data, [
                'updated_at' => Carbon::now()
            ]));
    }

    public function subCommentList($ids)
    {
        if (empty($ids))
        {
            return [];
        }

        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->getSubCommentItem($id);
            if ($item) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function mainCommentList($ids)
    {
        if (empty($ids))
        {
            return [];
        }

        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->getMainCommentItem($id);
            if ($item) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function deleteSubComment($id, $userId, $isRobot = false)
    {
        $comment = $this->getSubCommentItem($id);

        if (is_null($comment))
        {
            return null;
        }

        if (!$isRobot && ($comment['from_user_id'] !== intval($userId)))
        {
            return false;
        }

        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'state' => $isRobot ? 2 : 4,
                'deleted_at' => Carbon::now()
            ]);

        $this->ListRemove($this->getParentIdsKey($comment['parent_id']), $id);
        $this->writeCommentCount($comment['parent_id'], false);

        return $comment;
    }

    public function deleteMainComment($id, $userId)
    {
        $comment = $this->getMainCommentItem($id);

        if (is_null($comment))
        {
            return null;
        }

        if ($comment['from_user_id'] !== intval($userId))
        {
            return false;
        }

        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'state' => 4,
                'deleted_at' => Carbon::now()
            ]);

        $this->ListRemove($this->getModalIdsKey($comment['modal_id']), $id);
        $this->ListRemove($this->userCommentCacheKey($userId), $id);

        return $comment;
    }

    public function getMainCommentIds($modalId, $page = 0, $count = 10)
    {
        $ids = $this->RedisList($this->getModalIdsKey($modalId), function () use ($modalId)
        {
            return DB::table($this->table)
                ->where('modal_id', $modalId)
                ->orderBy('id', $this->order)
                ->pluck('id');
        });

        return $page === -1 ? $ids : array_slice($ids, $page * $count, $count);
    }

    public function getSubCommentIds($parentId, $page = 0, $count = 10)
    {
        $ids = $this->RedisList($this->getParentIdsKey($parentId), function () use ($parentId)
        {
            return DB::table($this->table)
                ->where('parent_id', $parentId)
                ->orderBy('id', $this->order)
                ->pluck('id');
        });

        return array_slice($ids, $page * $count, $count);
    }

    public function getUserCommentIds($userId, $page = 0, $count = 10)
    {
        $ids = $this->RedisList($this->userCommentCacheKey($userId), function () use ($userId)
        {
            return DB::table($this->table)
                ->whereRaw('user_id = ? and modal_id <> 0', [$userId])
                ->orderBy('id', 'DESC')
                ->pluck('id');
        });

        return $page === -1 ? $ids : array_slice($ids, $page * $count, $count);
    }

    public function getSubCommentItem($id, $force = false)
    {
        if (config('app.env') === 'local')
        {
            $force = true;
        }

        $result = $this->RedisHash($this->subCommentCacheKey($id), function () use ($id, $force)
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
                    "$tableName.comment_count",
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

        return $this->transformer()->sub($result);
    }

    public function getMainCommentItem($id, $force = false)
    {
        $cacheKey = $this->mainCommentCacheKey($id);

        if (is_null($cacheKey))
        {
            return null;
        }

        if (config('app.env') === 'local')
        {
            $force = true;
        }

        $result = $this->Cache($cacheKey, function () use ($id, $force)
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
                ->when($this->floor_count, function ($query) use ($tableName)
                {
                    return $query->select(
                        "$tableName.floor_count",
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
                    );
                }, function ($query) use ($tableName)
                {
                    return $query->select(
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
                    );
                })
                ->first();

            if (is_null($comment))
            {
                return null;
            }

            $content = json_decode($comment->content);
            $images = [];
            foreach ($content as $item)
            {
                if ($item->type === 'txt')
                {
                    $comment->content = $item->data;
                }
                else if ($item->type === 'img')
                {
                    $images[] = $item->data;
                }
            }
            $comment->images = $images;

            return json_decode(json_encode($comment), true);
        });

        if (is_null($result))
        {
            return null;
        }

        $counter = new CounterService($this->table, 'comment_count');
        $result['comment_count'] = $counter->get($result['id']);

        $result = $this->transformer()->main($result);
        $commentIds = $this->getSubCommentIds($result['id']);
        $result['comments'] = $this->subCommentList($commentIds);

        return $result;
    }

    public function check($userId, $modalId)
    {
        if (!$userId)
        {
            return false;
        }

        return (boolean)DB::table($this->table)
            ->whereRaw('user_id = ? and modal_id = ?', [$userId, $modalId])
            ->count();
    }

    protected function writeCommentCount($mainCommentId, $isPlus)
    {
        DB::table($this->table)
            ->where('id', $mainCommentId)
            ->increment('comment_count', $isPlus ? 1 : -1);

        $countService = new CounterService($this->table, 'comment_count');
        $countService->add($mainCommentId, $isPlus ? 1 : -1);
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
        return $this->table . '_' . $parentId . '_sub_comment_ids';
    }

    protected function getModalIdsKey($modalId)
    {
        return $this->table . '_' . $modalId . '_main_comment_ids';
    }

    protected function subCommentCacheKey($id)
    {
        return $this->table . '_sub_' . $id;
    }

    protected function mainCommentCacheKey($id)
    {
        $updatedAt = DB::table($this->table)
            ->where('id', $id)
            ->pluck('updated_at')
            ->first();

        if (!$updatedAt)
        {
            return null;
        }

        return $this->table . '_main_' . $id . '_' . strtotime($updatedAt);
    }

    protected function userCommentCacheKey($userId)
    {
        return $this->table . '_user_' . $userId . '_reply_ids';
    }
}