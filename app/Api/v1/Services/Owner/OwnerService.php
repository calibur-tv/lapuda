<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/5
 * Time: 下午5:18
 */

namespace App\Api\V1\Services\Owner;


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
    protected $modal_table;
    protected $stats_table;
    protected $max_count;

    public function __construct($modal_table, $stats_table, $max_count = 0)
    {
        $this->modal_table = $modal_table;
        $this->stats_table = $stats_table;
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
            $count = DB::table($this->stats_table)
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
        DB::table($this->stats_table)
            ->insert([
                'modal_id' => $modalId,
                'user_id' => $userId,
                'is_leader' => $isLeader ? time() : 0,
                'score' => 0,
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

        DB::table($this->stats_table)
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

        DB::table($this->stats_table)
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

        return (boolean)DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->count();
    }

    public function isLeader($modalId, $userId)
    {
        if (!$modalId || !$userId)
        {
            return false;
        }

        return (boolean)DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and user_id = ? and is_leader <> 0', [$modalId, $userId])
            ->count();
    }

    public function hasLeader($modalId)
    {
        if (!$modalId)
        {
            return false;
        }

        return (boolean)DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and is_leader <> 0', [$modalId])
            ->count();
    }

    public function remove($modalId, $userId)
    {
        if (!$this->isOwner($modalId, $userId))
        {
            return false;
        }

        DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->delete();

        Redis::DEL($this->ownersCacheKey($modalId));

        return true;
    }

    public function getOwners($modalId)
    {
        return $this->Cache($this->ownersCacheKey($modalId), function () use ($modalId)
        {
            $users = DB::table($this->stats_table)
                ->where('modal_id', $modalId)
                ->select('user_id', 'score', 'is_leader', 'created_at')
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

                $users[$i]->score = (int)$item->score;
                $users[$i]->is_leader = (int)$item->is_leader;
                $users[$i]->user = $userTransformer->item($user);
                unset($users[$i]->user_id);
            }

            return $users;
        });
    }

    public function changeScore($userId, $modalId, $score = 1)
    {
        DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->increment('score', $score);
    }

    protected function ownersCacheKey($modalId)
    {
        return $this->stats_table . '_' . $modalId . '_owners';
    }
}
