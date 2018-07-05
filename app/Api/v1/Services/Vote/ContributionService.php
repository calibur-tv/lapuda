<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/5
 * Time: 下午5:18
 */

namespace App\Api\V1\Services\Vote;


use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\UserTransformer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ContributionService extends Repository
{
    /**
     * ContributionService 贡献计数服务.
     * 贡献可能是 + 也可能是 -
     * 结果可能是正的，也可能是负的
     * 贡献者可以搞个 leader，只有 1 个 leader
     */
    protected $modal_table;
    protected $stats_table;
    protected $modal_field;
    protected $max_count;

    public function __construct($modal_table, $stats_table, $modal_field, $max_count = 0)
    {
        $this->modal_table = $modal_table;
        $this->stats_table = $stats_table;
        $this->modal_field = $modal_field;
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
            $count = DB::table($this->modal_table)
                ->where('id', $modalId)
                ->pluck($this->modal_field);

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

        return true;
    }

    public function isOwner($modalId, $userId)
    {
        return (boolean)DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->count();
    }

    public function isLeader($modalId, $userId)
    {
        return (boolean)DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and user_id = ? and is_leader <> 0', [$modalId, $userId])
            ->count();
    }

    public function hasLeader($modalId)
    {
        return (boolean)DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and is_leader <> 0', [$modalId])
            ->count();
    }

    public function ownerIds($modalId, $page, $take = 15)
    {
        $ids = $this->RedisList($this->ownerIdsCacheKey($modalId), function () use ($modalId)
        {
            return DB::table($this->stats_table)
                ->where('modal_id', $modalId)
                ->pluck('user_id');
        });

        return $this->filterIdsByPage($ids, $page, $take);
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

        Redis::DEL($this->ownerIdsCacheKey($modalId));

        return true;
    }

    public function getOwnersWithScore($modalId, $page, $take)
    {
        $users = DB::table($this->stats_table)
            ->where('modal_id', $modalId)
            ->take($take)
            ->skip(($page - 1) * $take)
            ->select('user_id', 'score', 'is_leader')
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
            $user = $userRepository->item($item['user_id']);
            if (is_null($user))
            {
                continue;
            }

            $users[$i]['score'] = (int)$item['score'];
            $users[$i]['is_leader'] = (int)$item['is_leader'];
            $users[$i]['user'] = $userTransformer->item($user);
            unset($users[$i]['user_id']);
        }

        return $users;
    }

    public function changeScore($userId, $modalId, $score = 1)
    {
        DB::table($this->stats_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->increment('score', $score);
    }

    protected function ownerIdsCacheKey($modalId)
    {
        return $this->stats_table . '_' . $modalId . '_owner_ids';
    }
}
