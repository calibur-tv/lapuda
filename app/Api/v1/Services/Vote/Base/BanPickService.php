<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/5
 * Time: 下午5:13
 */

namespace App\Api\V1\Services\Vote\Base;

use App\Api\V1\Services\Counter\BanPickReallyCounter;
use App\Api\V1\Services\Counter\BanPickShowCounter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BanPickService
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

    public function like($userId, $modalId)
    {
        $voteId = $this->getVotedId($userId, $modalId);

        if (!$voteId) // 没投过票
        {
            // 第一次点了赞同
            $voteId = $this->firstVoteIt($userId, $modalId, $this->score);

            $this->SortAdd($this->newsCacheListKey($modalId), $userId);
        }
        else // 已经投过票了
        {
            $votedScore = $this->getVotedScore($voteId);
            if ($votedScore > 0) // 连续点了两次赞同
            {
                DB::table($this->table)->delete($voteId);

                $this->SortRemove($this->newsCacheListKey($modalId), $userId);
                $this->changeModalScore($modalId, -$this->score);
            }
            else  // 先点反对，再点赞同
            {
                DB::table($this->table)
                    ->where('id', $voteId)
                    ->update([
                        'updated_at' => Carbon::now(),
                        'score' => $this->score
                    ]);

                $this->SortAdd($this->newsCacheListKey($modalId), $userId);
                $this->changeModalScore($modalId, 2 * $this->score);
            }
        }

        return $voteId;
    }

    public function dislike($userId, $modalId)
    {
        $voteId = $this->getVotedId($userId, $modalId);

        if (!$voteId) // 第一次点了反对
        {
            $voteId = $this->firstVoteIt($userId, $modalId, -$this->score);
        }
        else // 投过票了
        {
            $votedScore = $this->getVotedScore($voteId);

            if ($votedScore < 0) // 连续点了两次反对
            {
                DB::table($this->table)->delete($voteId);

                $this->changeModalScore($modalId, $this->score);
            }
            else // 先点赞同，再点反对
            {
                DB::table($this->table)
                    ->where('id', $voteId)
                    ->update([
                        'updated_at' => Carbon::now(),
                        'score' => -$this->score
                    ]);

                $this->SortRemove($this->newsCacheListKey($modalId), $userId);
                $this->changeModalScore($modalId, -2 * $this->score);
            }
        }

        return $voteId;
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

    public function batchCheck($list, $userId, $key = 'voted')
    {
        $ids = array_map(function ($item)
        {
            return $item['id'];
        }, $list);

        $results = DB::table($this->table)
            ->where('user_id', $userId)
            ->whereIn('modal_id', $ids)
            ->pluck('modal_id AS id', 'score')
            ->toArray();

        foreach ($list as $i => $item)
        {
            $list[$i][$key] = 0;
        }

        foreach ($results as $row)
        {
            foreach ($list as $i => $item)
            {
                if ($item['id'] === $row['id'])
                {
                    $list[$i][$key] = $row['score'];
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

    protected function firstVoteIt($userId, $modalId, $score)
    {
        $now = Carbon::now();
        $newId = DB::table($this->table)
            ->insertGetId([
                'user_id' => $userId,
                'modal_id' => $modalId,
                'score' => $score,
                'created_at' => $now,
                'updated_at' => $now
            ]);

        $this->changeModalScore($modalId, $score);

        return $newId;
    }

    protected function changeModalScore($modalId, $score)
    {
        if ($score > 0)
        {
            $banPickShowCounter = new BanPickShowCounter($this->table);
            $banPickShowCounter->add($modalId, $score);
        }
        $banPickReallyCounter = new BanPickReallyCounter($this->table);
        $banPickReallyCounter->add($modalId, $score);
    }

    protected function getVotedScore($id)
    {
        return (int)DB::table($this->table)
            ->where('id', $id)
            ->pluck('score');
    }

    protected function newsCacheListKey($modalId)
    {
        return $this->table . '_' . $modalId . 'new_user_ids';
    }

    protected function getVotedId($userId, $modalId)
    {
        if (!$userId || !$modalId)
        {
            return 0;
        }

        return (int)DB::table($this->table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->pluck('id');
    }
}