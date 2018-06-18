<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/5/22
 * Time: 上午7:00
 */

namespace App\Api\V1\Services\Comment;

use App\Api\V1\Repositories\Repository;
use App\Api\V1\Services\Counter\CommentCounterService;
use App\Api\V1\Services\Counter\CounterService;
use App\Api\V1\Services\Toggle\ToggleService;
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
    protected $modal;
    protected $table;
    protected $order;
    protected $author_sort;
    protected $commentTransformer;
    protected $like_table;
    protected $modal_table;
    protected $comment_count_field = 'comment_count';

    public function __construct($modal, $order = 'ASC', $author_sort = false)
    {
        $this->modal = $modal;
        $this->table = $modal . '_comments';
        $this->like_table = $modal . '_comment_like';
        $this->modal_table = $modal . 's';

        $this->order = $order === 'ASC' ? 'ASC' : 'DESC';
        $this->author_sort = $author_sort;
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

        $isMaster = false;
        if ($userId === $toUserId)
        {
            $isMaster = true;
            $toUserId = 0;
        }

        if ($modalId)
        {
            $count = DB::table($this->table)
                ->where('modal_id', $modalId)
                ->count();

            $id = DB::table($this->table)->insertGetId([
                'content' => $content,
                'user_id' => $userId,
                'to_user_id' => $toUserId,
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
            $this->changeSubCommentCount($parentId, $id, true);

            $job = (new \App\Jobs\Trial\Comment\CreateSubComment($this->modal, $id));
            dispatch($job);

            return $this->getSubCommentItem($id);
        }

        if ($modalId)
        {
            $this->changeMainCommentCount($modalId, $id, $userId, $isMaster, true);

            $job = (new \App\Jobs\Trial\Comment\CreateMainComment($this->modal, $id));
            dispatch($job);

            return $this->getMainCommentItem($id);
        }

        return null;
    }

    public function update($id, array $data)
    {
        // TODO
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

    public function deleteSubComment($id, $parentId, $isRobot = false)
    {
        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'state' => $isRobot ? 2 : 4,
                'deleted_at' => Carbon::now()
            ]);

        $this->changeSubCommentCount($parentId, $id, false);
    }

    public function deleteMainComment($id, $modalId, $userId = 0, $isMaster = false, $isRobot = false)
    {
        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'state' => $isRobot ? 2 : 4,
                'deleted_at' => Carbon::now()
            ]);

        $this->changeMainCommentCount($modalId, $id, $userId, $isMaster, false);
    }

    public function getMainCommentIds($modalId)
    {
        return $this->RedisList($this->mainCommentIdsKey($modalId), function () use ($modalId)
        {
            return DB::table($this->table)
                ->where('modal_id', $modalId)
                ->whereNull('deleted_at')
                ->orderBy('id', $this->order)
                ->pluck('id');
        });
    }

    public function getAuthorMainCommentIds($modalId, $authorId)
    {
        if (!$this->author_sort)
        {
            return $this->getMainCommentIds($modalId);
        }
        return $this->RedisList($this->authorMainCommentIdsKey($modalId), function () use ($modalId, $authorId)
        {
            return DB::table($this->table)
                ->where('modal_id', $modalId)
                ->where('user_id', $authorId)
                ->whereNull('deleted_at')
                ->orderBy('id', $this->order)
                ->pluck('id');
        });
    }

    public function getSubCommentIds($parentId)
    {
        return $this->RedisList($this->subCommentIdsKey($parentId), function () use ($parentId)
        {
            return DB::table($this->table)
                ->where('parent_id', $parentId)
                ->whereNull('deleted_at')
                ->orderBy('id', 'ASC')
                ->pluck('id');
        });
    }

    public function getUserCommentIds($userId)
    {
        return $this->RedisList($this->userCommentIdsKey($userId), function () use ($userId)
        {
            return DB::table($this->table)
                ->whereRaw('user_id = ? and to_user_id <> ? and modal_id <> 0', [$userId, $userId])
                ->whereNull('deleted_at')
                ->orderBy('id', 'DESC')
                ->pluck('id');
        });
    }

    public function getSubCommentItem($id)
    {
        $result = $this->RedisHash($this->subCommentCacheKey($id), function () use ($id)
        {
            $tableName = $this->table;

            $comment = DB::table($tableName)
                ->where("$tableName.id", $id)
                ->whereNull("$tableName.deleted_at")
                ->leftJoin('users AS from', 'from.id', '=', "$tableName.user_id")
                ->leftJoin('users AS to', 'to.id', '=', "$tableName.to_user_id")
                ->select(
                    "$tableName.id",
                    "$tableName.content",
                    "$tableName.modal_id",
                    "$tableName.created_at",
                    "$tableName.to_user_id",
                    "$tableName.parent_id",
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

    public function getMainCommentItem($id)
    {
        $result = $this->Cache($this->mainCommentCacheKey($id), function () use ($id)
        {
            $tableName = $this->table;

            $comment = DB::table($tableName)
                ->where("$tableName.id", $id)
                ->whereNull("$tableName.deleted_at")
                ->leftJoin('users AS from', 'from.id', '=', "$tableName.user_id")
                ->leftJoin('users AS to', 'to.id', '=', "$tableName.to_user_id")
                ->select(
                    "$tableName.floor_count",
                    "$tableName.id",
                    "$tableName.content",
                    "$tableName.created_at",
                    "$tableName.modal_id",
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

        $result = $this->transformer()->main($result);

        $commentIds = $this->getSubCommentIds($id);
        if (count($commentIds))
        {
            $commentIdsObj = $this->filterIdsByMaxId($commentIds, 0, 5);
            $result['comments'] = [
                'list' => $this->subCommentList($commentIdsObj['ids']),
                'total' => $commentIdsObj['total'],
                'noMore' => $commentIdsObj['noMore']
            ];
        }
        else
        {
            $result['comments'] = [
                'list' => [],
                'total' => 0,
                'noMore' => true
            ];
        }

        return $result;
    }

    public function checkCommented($userId, $modalId)
    {
        if (!$userId)
        {
            return false;
        }

        return (boolean)DB::table($this->table)
            ->whereRaw('user_id = ? and modal_id = ?', [$userId, $modalId])
            ->count();
    }

    public function batchCheckCommented($list, $userId, $key = 'commented')
    {
        $ids = array_map(function ($item)
        {
            return $item['id'];
        }, $list);

        $results = DB::table($this->table)
            ->where('user_id', $userId)
            ->whereIn('modal_id', $ids)
            ->pluck('modal_id AS id')
            ->toArray();

        foreach ($list as $i => $item)
        {
            $list[$i][$key] = in_array($item['id'], $results);
        }

        return $list;
    }

    public function toggleLike($userId, $modalId)
    {
        $toggleService = new ToggleService(
            $this->table,
            $this->comment_count_field,
            $this->like_table
        );

        return $toggleService->toggle($userId, $modalId);
    }

    public function checkLiked($userId, $modalId)
    {
        $toggleService = $this->getCommentToggleLikeService();

        return $toggleService->check($userId, $modalId);
    }

    public function batchCheckLiked($list, $userId, $key = 'liked')
    {
        $toggleService = $this->getCommentToggleLikeService();

        return $toggleService->batchCheck($list, $userId, $key);
    }

    public function getLikeCount($modalId)
    {
        $toggleService = $this->getCommentToggleLikeService();

        return $toggleService->total($modalId);
    }

    public function batchGetLikeCount($list, $key = 'like_count')
    {
        $toggleService = $this->getCommentToggleLikeService();

        return $toggleService->batchTotal($list, $key);
    }

    public function getCommentCount($modalId)
    {
        $counterService = $this->getCommentCounterService();

        return $counterService->get($modalId);
    }

    public function batchGetCommentCount($list, $key = 'comment_count')
    {
        $counterService = $this->getCommentCounterService();

        return $counterService->batchGet($list, $key);
    }

    protected function changeMainCommentCount($modalId, $commentId, $userId, $isMaster, $isCreate)
    {
        if ($isCreate)
        {
            if ($this->order === 'ASC')
            {
                $this->ListInsertAfter($this->mainCommentIdsKey($modalId), $commentId);
                if ($isMaster && $this->author_sort)
                {
                    $this->ListInsertAfter($this->authorMainCommentIdsKey($modalId), $commentId);
                }
            }
            else
            {
                $this->ListInsertBefore($this->mainCommentIdsKey($modalId), $commentId);
                if ($isMaster && $this->author_sort)
                {
                    $this->ListInsertBefore($this->authorMainCommentIdsKey($modalId), $commentId);
                }
            }
            // 更新用户的回复列表
            $this->ListInsertBefore($this->userCommentIdsKey($userId), $commentId);
        }
        else
        {
            $this->ListRemove($this->mainCommentIdsKey($modalId), $commentId);
            // 不是楼主删层主
            if ($userId && !$isMaster)
            {
                $this->ListRemove($this->userCommentIdsKey($userId), $commentId);
            }
            if ($isMaster && $this->author_sort)
            {
                $this->ListRemove($this->authorMainCommentIdsKey($modalId), $commentId);
            }
        }

        $counterService = $this->getCommentCounterService();
        $isCreate ? $counterService->add($modalId) : $counterService->add($modalId, -1);
    }

    protected function changeSubCommentCount($mainCommentId, $subCommentId, $isCreate)
    {
        $isCreate
            ? $this->ListInsertAfter($this->subCommentIdsKey($mainCommentId), $subCommentId)
            : $this->ListRemove($this->subCommentIdsKey($mainCommentId), $subCommentId);

        DB::table($this->table)
            ->where('id', $mainCommentId)
            ->increment('comment_count', $isCreate ? 1 : -1);
    }

    protected function transformer()
    {
        if (!$this->commentTransformer)
        {
            $this->commentTransformer = new CommentTransformer();
        }

        return $this->commentTransformer;
    }

    protected function subCommentIdsKey($parentId)
    {
        return $this->table . '_' . $parentId . '_sub_comment_ids';
    }

    protected function mainCommentIdsKey($modalId)
    {
        return $this->table . '_' . $modalId . '_main_comment_ids';
    }

    protected function authorMainCommentIdsKey($modalId)
    {
        return $this->table . '_' . $modalId . '_author_main_comment_ids';
    }

    protected function subCommentCacheKey($parentId)
    {
        return $this->table . '_sub_comment_' . $parentId;
    }

    protected function mainCommentCacheKey($modalId)
    {
        return $this->table . '_main_comment_' . $modalId;
    }

    protected function userCommentIdsKey($userId)
    {
        return $this->table . '_user_' . $userId . '_reply_ids';
    }

    protected function getCommentToggleLikeService()
    {
        return new ToggleService(
            $this->table,
            $this->comment_count_field,
            $this->like_table
        );
    }

    protected function getCommentCounterService()
    {
        return new CommentCounterService(
            $this->table,
            $this->comment_count_field,
            $this->modal_table
        );
    }
}