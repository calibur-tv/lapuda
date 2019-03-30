<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/5
 * Time: 下午5:13
 */

namespace App\Api\V1\Services\Vote\Base;

use App\Api\V1\Repositories\Repository;
use App\Api\V1\Services\Counter\BanPickReallyCounter;
use App\Api\V1\Services\Counter\BanPickShowCounter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BanPickService extends Repository
{
    /**
     * BanPickService 非ban即选服务，如知乎的点赞.
     * 用 1 和 -1 标记 pick 和 ban，代表 赞同 和 不赞同
     * 但是显示的时候，只显示加的分数总和，不显示真实分数，因此需要两个 field
     */
    protected $table;
    protected $score;

    public function __construct($table, $score = 1)
    {
        $this->table = $table;
        $this->score = $score;
    }

    public function toggleLike($userId, $modalId)
    {
        $voteId = $this->getVotedId($userId, $modalId);

        if (!$voteId) // 没投过票
        {
            // 第一次点了赞同 -> 赞
            $this->firstVoteIt($userId, $modalId, $this->score);

            $this->ListInsertBefore($this->agreeUsersIdCacheKey($modalId), $userId);

            $result = $this->score;
        }
        else // 已经投过票了
        {
            $votedScore = $this->getVotedScore($voteId);
            if ($votedScore > 0) // 连续点了两次赞同 -> 取消赞
            {
                DB::table($this->table)->delete($voteId);

                $this->ListRemove($this->agreeUsersIdCacheKey($modalId), $userId);

                $banPickShowCounter = new BanPickShowCounter($this->table);
                $banPickShowCounter->add($modalId, -$this->score);

                $banPickReallyCounter = new BanPickReallyCounter($this->table);
                $banPickReallyCounter->add($modalId, -$this->score);

                $result = 0;
            }
            else  // 先点反对，再点赞同 -> 赞
            {
                DB::table($this->table)
                    ->where('id', $voteId)
                    ->update([
                        'score' => $this->score,
                        'created_at' => Carbon::now()
                    ]);

                $this->ListInsertBefore($this->agreeUsersIdCacheKey($modalId), $userId);
                $this->ListRemove($this->bannedUsersIdCacheKey($modalId), $userId);

                $banPickShowCounter = new BanPickShowCounter($this->table);
                $banPickShowCounter->add($modalId, $this->score);

                $banPickReallyCounter = new BanPickReallyCounter($this->table);
                $banPickReallyCounter->add($modalId, 2 * $this->score);

                $result = $this->score;
            }
        }

        return $result;
    }

    public function toggleDislike($userId, $modalId)
    {
        $voteId = $this->getVotedId($userId, $modalId);

        if (!$voteId) // 第一次点了反对 -> 反对
        {
            $this->firstVoteIt($userId, $modalId, -$this->score);

            $this->ListInsertBefore($this->bannedUsersIdCacheKey($modalId), $userId);

            $result = -$this->score;
        }
        else // 投过票了
        {
            $votedScore = $this->getVotedScore($voteId);

            if ($votedScore < 0) // 连续点了两次反对 -> 取消反对
            {
                DB::table($this->table)->delete($voteId);

                $this->ListRemove($this->bannedUsersIdCacheKey($modalId), $userId);

                $banPickReallyCounter = new BanPickReallyCounter($this->table);
                $banPickReallyCounter->add($modalId, $this->score);

                $result = 0;
            }
            else // 先点赞同，再点反对 -> 取消赞
            {
                DB::table($this->table)
                    ->where('id', $voteId)
                    ->update([
                        'score' => -$this->score,
                        'created_at' => Carbon::now()
                    ]);

                $this->ListInsertBefore($this->bannedUsersIdCacheKey($modalId), $userId);
                $this->ListRemove($this->agreeUsersIdCacheKey($modalId), $userId);

                $banPickShowCounter = new BanPickShowCounter($this->table);
                $banPickShowCounter->add($modalId, -$this->score);

                $banPickReallyCounter = new BanPickReallyCounter($this->table);
                $banPickReallyCounter->add($modalId, -2 * $this->score);

                $result = -$this->score;
            }
        }

        return $result;
    }

    public function check($userId, $modalId)
    {
        if (!$userId || !$modalId)
        {
            return 0;
        }

        return (int)DB::table($this->table)
            ->whereRaw('user_id = ? and modal_id = ?', [$userId, $modalId])
            ->pluck('score')
            ->first();
    }

    public function getVoteCount($id)
    {
        $banPickShowCount = new BanPickShowCounter($this->table);

        return $banPickShowCount->get($id);
    }

    public function batchCheck($list, $userId, $key = 'voted')
    {
        if (!$userId)
        {
            foreach ($list as $i => $item)
            {
                $list[$i][$key] = 0;
            }

            return $list;
        }

        $ids = array_map(function ($item)
        {
            return $item['id'];
        }, $list);

        $results = DB::table($this->table)
            ->where('user_id', $userId)
            ->whereIn('modal_id', $ids)
            ->select('modal_id AS id', 'score')
            ->get()
            ->toArray();

        foreach ($list as $i => $item)
        {
            $list[$i][$key] = 0;
        }

        foreach ($results as $row)
        {
            foreach ($list as $i => $item)
            {
                if ($item['id'] == $row->id)
                {
                    $list[$i][$key] = (int)$row->score;
                }
            }
        }

        return $list;
    }

    public function batchVote($list, $key = 'vote_count')
    {
        $banPickShowCount = new BanPickShowCounter($this->table);

        return $banPickShowCount->batchGet($list, $key);
    }

    public function agreeUsersId($modalId)
    {
        return $this->RedisList($this->agreeUsersIdCacheKey($modalId), function () use ($modalId)
        {
            return DB::table($this->table)
                ->where('modal_id', $modalId)
                ->where('score', '>', 0)
                ->orderBy('created_at', 'desc')
                ->pluck('user_id');
        });
    }

    public function bannedUsersId($modalId)
    {
        return $this->RedisList($this->bannedUsersIdCacheKey($modalId), function () use ($modalId)
        {
            return DB::table($this->table)
                ->where('modal_id', $modalId)
                ->where('score', '<', 0)
                ->orderBy('created_at', 'desc')
                ->pluck('user_id');
        });
    }

    protected function firstVoteIt($userId, $modalId, $score)
    {
        $newId = DB::table($this->table)
            ->insertGetId([
                'user_id' => $userId,
                'modal_id' => $modalId,
                'score' => $score,
                'created_at' => Carbon::now()
            ]);

        $banPickReallyCounter = new BanPickReallyCounter($this->table);
        $banPickReallyCounter->add($modalId, $score);

        if ($score > 0)
        {
            $banPickShowCounter = new BanPickShowCounter($this->table);
            $banPickShowCounter->add($modalId, $score);
        }

        return $newId;
    }

    protected function getVotedScore($id)
    {
        return (int)DB::table($this->table)
            ->where('id', $id)
            ->pluck('score')
            ->first();
    }

    protected function agreeUsersIdCacheKey($modalId)
    {
        return $this->table . '_' . $modalId . '_agree_user_ids';
    }

    protected function bannedUsersIdCacheKey($modalId)
    {
        return $this->table . '_' . $modalId . '_banned_user_ids';
    }

    protected function getVotedId($userId, $modalId)
    {
        if (!$userId || !$modalId)
        {
            return 0;
        }

        return (int)DB::table($this->table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->pluck('id')
            ->first();
    }
}