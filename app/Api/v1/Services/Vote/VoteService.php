<?php

namespace App\Api\V1\Services\Vote;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Counter\ToggleCountService;
use App\Api\V1\Services\Counter\VoteCountService;
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
class VoteService extends Repository
{
    protected $modal_table;
    protected $modal_field;
    protected $vote_table;
    protected $zero_delete;
    protected $is_ab_score;
    protected $need_cache_list;

    /**
     * ab_score：
     * 类似知乎那种，只有 like 和 dislike
     *
     * zero_delete：
     * 如果这条数据的 score 为 0，那就删除记录
     */

    public function __construct(
        $modalTable,
        $modalField,
        $voteTable,
        $ifZeroDelete = true,
        $isABScore = true,
        $needCacheList = true
    )
    {
        $this->modal_table = $modalTable;
        $this->modal_field = $modalField;
        $this->vote_table = $voteTable;
        $this->zero_delete = $ifZeroDelete;
        $this->is_ab_score = $isABScore;
        $this->need_cache_list = $needCacheList;
    }

    public function check($userId, $modalId)
    {
        if (!$userId || !$modalId)
        {
            return false;
        }

        return (boolean)$this->getVotedId($userId, $modalId);
    }

    public function like($userId, $modalId, $score = 1)
    {
        $voteId = $this->getVotedId($userId, $modalId);

        if (!$voteId) // 没投过票
        {
            // 第一次点了赞同
            $voteId = $this->firstVoteIt($userId, $modalId, $score);

            if ($this->need_cache_list)
            {
                $this->SortAdd($this->activeCacheListKey($modalId), $userId);
                if (!$this->is_ab_score)
                {
                    $this->SortAdd($this->hotsCacheListKey($modalId), $userId, $score);
                }
            }
        }
        else // 已经投过票了
        {
            $votedScore = $this->getVotedScore($voteId);
            if ($this->is_ab_score) // 如果是 like-dislike 模型
            {
                if ($votedScore > 0) // 连续点了两次赞同
                {
                    DB::table($this->vote_table)->delete($voteId);

                    if ($this->need_cache_list)
                    {
                        $this->SortRemove($this->activeCacheListKey($modalId), $userId);
                    }

                    $this->changeModalScore($modalId, -$score);
                }
                else  // 先点反对，再点赞同
                {
                    DB::table($this->vote_table)
                        ->where('id', $voteId)
                        ->update([
                            'updated_at' => Carbon::now(),
                            'score' => $score
                        ]);

                    if ($this->need_cache_list)
                    {
                        $this->SortAdd($this->activeCacheListKey($modalId), $userId);
                    }

                    $this->changeModalScore($modalId, 2 * $score);
                }
            }
            else // 如果不是 like-dislike 的，是友善度模型
            {
                $resultScore = $votedScore + $score;
                if ($this->zero_delete && $resultScore === 0)
                {
                    // 之前友善度一直是负值，现在清零了，就从敏感用户列表里移除
                    DB::table($this->vote_table)->delete($voteId);
                }
                else
                {
                    // 友善度不断的提升
                    DB::table($this->vote_table)
                        ->where('id', $voteId)
                        ->increment('score', $score);
                    DB::table($this->vote_table)
                        ->where('id', $voteId)
                        ->update([
                            'updated_at' => Carbon::now()
                        ]);
                }
                if ($resultScore > 0 && $this->need_cache_list)
                {
                    // 友善度已经大于 0 了，放入好人排行榜
                    $this->SortAdd($this->activeCacheListKey($modalId), $userId);
                    $this->SortAdd($this->hotsCacheListKey($modalId), $userId, $resultScore);
                }

                $this->changeModalScore($modalId, $score);
            }
        }

        return $voteId;
    }

    public function dislike($userId, $modalId, $score = -1)
    {
        $voteId = $this->getVotedId($userId, $modalId);

        if (!$voteId) // 第一次点了反对
        {
            $voteId = $this->firstVoteIt($userId, $modalId, $score);
        }
        else // 投过票了
        {
            $votedScore = $this->getVotedScore($voteId);

            if ($this->is_ab_score) // like-dislike 模型
            {
                if ($votedScore < 0) // 连续点了两次反对
                {
                    DB::table($this->vote_table)->delete($voteId);

                    $this->changeModalScore($modalId, -$score);
                }
                else // 先点赞同，再点反对
                {
                    DB::table($this->vote_table)
                        ->where('id', $voteId)
                        ->update([
                            'updated_at' => Carbon::now(),
                            'score' => $score
                        ]);

                    if ($this->need_cache_list)
                    {
                        $this->SortRemove($this->activeCacheListKey($modalId), $userId);
                    }

                    $this->changeModalScore($modalId, 2 * $score);
                }
            }
            else // 如果不是 like-dislike 的，是友善度模型
            {
                $resultScore = $votedScore + $score;
                if ($this->zero_delete && $resultScore === 0)
                {
                    // 友善度降为 0 了
                    DB::table($this->vote_table)->delete($voteId);
                }
                else
                {
                    // increment， -1 -> 12
                    DB::table($this->vote_table)
                        ->where('id', $voteId)
                        ->increment('score', $score);
                    DB::table($this->vote_table)
                        ->where('id', $voteId)
                        ->update([
                            'updated_at' => Carbon::now()
                        ]);
                }
                if ($this->need_cache_list)
                {
                    if ($resultScore <= 0)
                    {
                        // 友善度 <= 0 了，从好人排行榜里移除
                        $this->SortRemove($this->activeCacheListKey($modalId), $userId);
                        $this->SortRemove($this->hotsCacheListKey($modalId), $userId);
                    }
                    else
                    {
                        // 友善度仍然大于 0 ，改变友善度的值
                        $this->SortAdd($this->hotsCacheListKey($modalId), $userId, $resultScore);
                    }
                }

                $this->changeModalScore($modalId, $score);
            }
        }

        return $voteId;
    }

    public function userVotes($userId, $modalId)
    {
        if (!$userId || !$modalId)
        {
            return 0;
        }

        return (int)DB::table($this->vote_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->pluck('score');
    }

    public function voteTotal($modalId)
    {
        $counterService = new VoteCountService($this->modal_table, $this->modal_field, $this->vote_table);

        return $counterService->get($modalId);
    }

    public function hotsUserIds($modalId, $seenIds)
    {
        if ($this->is_ab_score)
        {
            return [];
        }

        $ids = $this->RedisSort($this->hotsCacheListKey($modalId), function () use ($modalId)
        {
            return DB::table($this->vote_table)
                ->where('modal_id', $modalId)
                ->orderBy('score', 'desc')
                ->pluck('score', 'user_id AS id');
        }, false, true);

        if (empty($ids))
        {
            return [];
        }

        foreach ($ids as $key => $val)
        {
            if (in_array($key, $seenIds))
            {
                unset($ids[$key]);
            }
        }

        return array_slice($ids, 0, config('website.list_count'), true);
    }

    public function activeUserIds($modalId, $seenIds)
    {
        $ids = $this->RedisSort($this->hotsCacheListKey($modalId), function () use ($modalId)
        {
            return DB::table($this->vote_table)
                ->where('modal_id', $modalId)
                ->orderBy('updated_at', 'desc')
                ->pluck('updated_at', 'user_id AS id');
        }, true, true);

        if (empty($ids))
        {
            return [];
        }

        foreach ($ids as $key => $val)
        {
            if (in_array($key, $seenIds))
            {
                unset($ids[$key]);
            }
        }

        return array_slice($ids, 0, config('website.list_count'), true);
    }

    public function oldUserIds($modalId, $page, $take)
    {

    }

    public function trendingModalIds()
    {

    }

    protected function changeModalScore($modalId, $score)
    {
        $counterService = new VoteCountService($this->modal_table, $this->modal_field, $this->vote_table);
        $counterService->add($modalId, $score);
    }

    protected function firstVoteIt($userId, $modalId, $score)
    {
        $now = Carbon::now();
        $newId = DB::table($this->vote_table)
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

    protected function getVotedId($userId, $modalId)
    {
        if (!$userId || !$modalId)
        {
            return 0;
        }

        return (int)DB::table($this->vote_table)
            ->whereRaw('modal_id = ? and user_id = ?', [$modalId, $userId])
            ->pluck('id');
    }

    protected function getVotedScore($id)
    {
        return DB::table($this->vote_table)
            ->where('id', $id)
            ->pluck('score');
    }

    protected function activeCacheListKey($modalId)
    {
        return $this->vote_table . '_' . $modalId . 'news';
    }

    protected function hotsCacheListKey($modalId)
    {
        return $this->vote_table . '_' . $modalId . 'hots';
    }

    protected function trendingModalCacheKey()
    {
        return $this->modal_table . '_' . $this->modal_field . '_trending_ids';
    }
}