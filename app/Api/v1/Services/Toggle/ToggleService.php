<?php

namespace App\Api\V1\Services\Toggle;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Counter\Base\RelationCounterService;
use App\Api\V1\Transformers\UserTransformer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Api\V1\Repositories\Repository;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午3:27
 */
class ToggleService extends Repository
{
    protected $table;

    public function __construct($toggleTable)
    {
        $this->table = $toggleTable;
    }

    public function do($userId, $modalId, $count = 1)
    {
        $id = DB::table($this->table)
            ->insertGetId([
                'user_id' => $userId,
                'modal_id' => $modalId,
                'created_at' => Carbon::now()
            ]);

        $relationCounterService = new RelationCounterService($this->table);
        $relationCounterService->add($modalId, $count);

        $this->SortAdd($this->doUsersCacheKey($modalId), $userId);
        $this->SortAdd($this->usersDoCacheKey($userId), $modalId);

        return $id;
    }

    public function undo($userId, $modalId)
    {
        DB::table($this->table)
            ->where('user_id', $userId)
            ->where('modal_id', $modalId)
            ->delete();

        $relationCounterService = new RelationCounterService($this->table);
        $relationCounterService->add($modalId, -1);

        $this->SortRemove($this->doUsersCacheKey($modalId), $userId);
        $this->SortRemove($this->usersDoCacheKey($userId), $modalId);

        return 0;
    }

    public function usersDoTotal()
    {
        // 还没有这个表
        // user_stats?
    }

    public function check($userId, $modalId, $modalCreatorId = null)
    {
        if (!$userId)
        {
            return false;
        }

        return (boolean)
            DB::table($this->table)
            ->whereRaw('user_id = ? and modal_id = ?', [$userId, $modalId])
            ->count();
    }

    public function batchCheck($list, $userId, $key)
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

    public function toggle($userId, $modalId)
    {
        $Gone = $this->check($userId, $modalId);

        return $Gone ? $this->undo($userId, $modalId) : $this->do($userId, $modalId);
    }

    public function total($modalId)
    {
        $relationCounterService = new RelationCounterService($this->table);

        return $relationCounterService->get($modalId);
    }

    public function batchTotal($list, $key)
    {
        $relationCounterService = new RelationCounterService($this->table);

        return $relationCounterService->batchGet($list, $key);
    }

    public function users($modalId, $lastId = 0, $count = 10)
    {
        $idsObj = $this->doUsersIds($modalId, $lastId, $count);
        $ids = $idsObj['ids'];
        if (empty($ids))
        {
            return [
                'list' => [],
                'total' => 0,
                'noMore' => true
            ];
        }

        $userRepository = new UserRepository();
        $users = [];

        foreach ($ids as $id => $createdAt)
        {
            $user = $userRepository->item($id);
            if (is_null($user))
            {
                continue;
            }
            $user['created_at'] = $createdAt;
            $users[] = $user;
        }

        $userTransformer = new UserTransformer();

        return [
            'list' => $userTransformer->toggleUsers($users),
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ];
    }
    // 某个用户的文章收藏列表
    public function usersDoIds($userId, $page = 0, $count = 10)
    {
        $ids = $this->RedisList($this->usersDoCacheKey($userId), function () use ($userId)
        {
            return DB::table($this->table)
                ->where('user_id', $userId)
                ->orderBy('id', 'DESC')
                ->pluck('modal_id');

        }, 0, -1, 'm');

        if (empty($ids))
        {
            return [
                'ids' => [],
                'total' => 0,
                'noMore' => true
            ];
        }

        if ($page === -1)
        {
            return [
                'ids' => $ids,
                'total' => count($ids),
                'noMore' => true
            ];
        }

        return $this->filterIdsByPage($ids, $page, $count, true);
    }
    // 某篇文章的收藏者们
    protected function doUsersIds($modalId, $lastId, $count)
    {
        $ids = $this->RedisSort($this->doUsersCacheKey($modalId), function () use ($modalId)
        {
            return DB::table($this->table)
                ->where('modal_id', $modalId)
                ->orderBy('created_at', 'DESC')
                ->pluck('created_at', 'user_id AS id');

        }, true, true);

        if (empty($ids))
        {
            return [
                'ids' => [],
                'total' => 0,
                'noMore' => true
            ];
        }

        return $this->filterIdsByMaxId($ids, $lastId, $count, true);
    }

    protected function usersDoCacheKey($userId)
    {
        return 'user_' . $userId . '_' . $this->table . '_ids';
    }

    protected function doUsersCacheKey($modalId)
    {
        return $this->table . '_' . $modalId . '_ids';
    }
}