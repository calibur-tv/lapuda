<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/5
 * Time: 下午5:18
 */

namespace App\Api\V1\Services\Owner\Base;


use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\UserTransformer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OwnerService extends Repository
{
    /**
     * OwnerService 贡献计数服务.
     * 贡献可能是 + 也可能是 -
     * 结果可能是正的，也可能是负的
     * 贡献者可以搞个 leader，只有 1 个 leader
     */
    protected $owner_table;
    protected $log_table;
    protected $max_count;

    public function __construct($owner_table, $max_count = 0)
    {
        $this->owner_table = $owner_table;
        $this->log_table = $owner_table . '_log';
        $this->max_count = $max_count;
    }

    public function set($modalId, $userId, $isLeader = false)
    {
        if ($this->isOwner($modalId, $userId))
        {
            return false;
        }

        if ($this->max_count)
        {
            $count = DB::table($this->owner_table)
                ->where('modal_id', $modalId)
                ->count();

            if ($count >= $this->max_count)
            {
                return false;
            }
        }

        if ($isLeader && $this->hasLeader($modalId))
        {
            return false;
        }

        $now = Carbon::now();
        DB::table($this->owner_table)
            ->insert([
                'modal_id' => $modalId,
                'user_id' => $userId,
                'is_leader' => $isLeader ? time() : 0,
                'created_at' => $now,
                'updated_at' => $now
            ]);

        Redis::DEL($this->ownersCacheKey($modalId));

        return true;
    }

    public function upgrade($modalId, $userId)
    {
        if (!$this->isOwner($modalId, $userId))
        {
            return false;
        }
        if ($this->hasLeader($modalId))
        {
            return false;
        }
        if ($this->isLeader($modalId, $userId))
        {
            return false;
        }

        DB::table($this->owner_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->update([
                'is_leader' => time()
            ]);

        Redis::DEL($this->ownersCacheKey($modalId));

        return true;
    }

    public function downgrade($modalId, $userId)
    {
        if ($this->isLeader(!$modalId, $userId))
        {
            return false;
        }

        DB::table($this->owner_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->update([
                'is_leader' => 0
            ]);

        Redis::DEL($this->ownersCacheKey($modalId));

        return true;
    }

    public function isOwner($modalId, $userId)
    {
        if (!$modalId || !$userId)
        {
            return false;
        }

        return (boolean)DB::table($this->owner_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->count();
    }

    public function isLeader($modalId, $userId)
    {
        if (!$modalId || !$userId)
        {
            return false;
        }

        return (boolean)DB::table($this->owner_table)
            ->whereRaw('modal_id = ? and user_id = ? and is_leader <> 0', [$modalId, $userId])
            ->count();
    }

    public function hasLeader($modalId)
    {
        if (!$modalId)
        {
            return false;
        }

        return (boolean)DB::table($this->owner_table)
            ->whereRaw('modal_id = ? and is_leader <> 0', [$modalId])
            ->count();
    }

    public function remove($modalId, $userId)
    {
        if (!$this->isOwner($modalId, $userId))
        {
            return false;
        }

        DB::table($this->owner_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->delete();

        Redis::DEL($this->ownersCacheKey($modalId));

        return true;
    }

    public function users($modalId)
    {
        $result = $this->Cache($this->ownersCacheKey($modalId), function () use ($modalId)
        {
            $users = DB::table($this->owner_table)
                ->where('modal_id', $modalId)
                ->select('user_id', 'is_leader', 'created_at')
                ->get();

            if (is_null($users))
            {
                return [];
            }

            $users = $users->toArray();
            $userRepository = new UserRepository();
            $userTransformer = new UserTransformer();

            foreach ($users as $i => $item)
            {
                $user = $userRepository->item($item->user_id);
                if (is_null($user))
                {
                    continue;
                }

                $users[$i]->is_leader = (int)$item->is_leader;
                $users[$i]->user = $userTransformer->item($user);
                unset($users[$i]->user_id);
            }

            return $users;
        });

        return [
            'list' => $result,
            'total' => count($result),
            'noMore' => true
        ];
    }

    public function total($modalId)
    {
        // 统一接口
        return 0;
    }

    public function setLog($userId, $modalId ,$content, $type)
    {
        if (!$this->isOwner($modalId, $userId))
        {
            $this->set($modalId, $userId, !$this->hasLeader($modalId));
        }

        $now = Carbon::now();

        $newId = DB::table($this->log_table)
            ->insertGetId([
                'user_id' => $userId,
                'modal_id' => $modalId,
                'content' => $content,
                'type' => $type,
                'created_at' => $now,
                'updated_at' => $now
            ]);

        Redis::DEL($this->logsCacheKey($modalId));

        return $newId;
    }

    public function getLogs($modalId)
    {
        return $this->Cache($this->logsCacheKey($modalId), function () use ($modalId)
        {
            return DB::table($this->log_table)
                ->where('modal_id', $modalId)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->toArray();
        });
    }

    public function deleteLog($userId, $logId)
    {
        $modalId = DB::table($this->log_table)
            ->where('id', $logId)
            ->where('user_id', $userId)
            ->pluck('modal_id')
            ->first();

        if (!$modalId)
        {
            return '不存在的记录';
        }

        $count = DB::table($this->log_table)
            ->where('modal_id', $modalId)
            ->count();

        if ($count <= 1)
        {
            return '至少要保留一条记录';
        }

        $firstId = DB::table($this->log_table)
            ->orderBy('id', 'ASC')
            ->where('modal_id', $modalId)
            ->pluck('id')
            ->first();

        if ($firstId == $modalId)
        {
            return '不能删除最初的记录';
        }

        DB::table($this->log_table)
            ->where('id', $logId)
            ->update([
                'deleted_at' => Carbon::now()
            ]);

        $userLogCount = DB::table($this->log_table)
            ->where('modal_id', $modalId)
            ->where('user_id', $userId)
            ->count();

        if (!$userLogCount)
        {
            $this->remove($modalId, $userId);
        }

        Redis::DEL($this->logsCacheKey($modalId));

        return '';
    }

    protected function logsCacheKey($modalId)
    {
        return $this->log_table . '_' . $modalId . '_logs';
    }

    protected function ownersCacheKey($modalId)
    {
        return $this->owner_table . '_' . $modalId . '_owners';
    }
}
