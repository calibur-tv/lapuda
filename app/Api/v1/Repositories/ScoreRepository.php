<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午4:09
 */

namespace App\Api\V1\Repositories;


use App\Api\V1\Services\Counter\Stats\TotalScoreCount;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Bangumi\BangumiScoreService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
use App\Models\Score;
use Illuminate\Support\Facades\Redis;

class ScoreRepository extends Repository
{
    public function item($id)
    {
        return $this->Cache($this->cacheKeyScoreItem($id), function () use ($id)
        {
            $score = Score::find($id);
            if (is_null($score))
            {
                return null;
            }

            $score = $score->toArray();
            $score['content'] = json_decode($score['content']);
            $score['total'] = $score['total'] / 10;
            $score['lol'] = $score['lol'] / 2;
            $score['cry'] = $score['cry'] / 2;
            $score['fight'] = $score['fight'] / 2;
            $score['moe'] = $score['moe'] / 2;
            $score['sound'] = $score['sound'] / 2;
            $score['vision'] = $score['vision'] / 2;
            $score['role'] = $score['role'] / 2;
            $score['story'] = $score['story'] / 2;
            $score['express'] = $score['express'] / 2;
            $score['style'] = $score['style'] / 2;

            return $score;
        });
    }

    public function list($ids)
    {
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

    public function bangumiScore($bangumiId)
    {
        return $this->Cache($this->cacheKeyBangumiScore($bangumiId), function () use ($bangumiId)
        {
            $data =  Score::where('bangumi_id', $bangumiId)
                ->select('total', 'lol', 'cry', 'fight', 'moe', 'sound', 'vision', 'role', 'story', 'express', 'style')
                ->whereNotNull('published_at')
                ->get()
                ->toArray();

            $count = count($data);
            if (!$count)
            {
                return null;
            }

            $total = 0;
            $lol = 0;
            $cry = 0;
            $fight = 0;
            $moe = 0;
            $sound = 0;
            $vision = 0;
            $role = 0;
            $story = 0;
            $express = 0;
            $style = 0;

            foreach ($data as $score)
            {
                $total += $score['total'];
                $lol += $score['lol'];
                $cry += $score['cry'];
                $fight += $score['fight'];
                $moe += $score['moe'];
                $sound += $score['sound'];
                $vision += $score['vision'];
                $role += $score['role'];
                $story += $score['story'];
                $express += $score['express'];
                $style += $score['style'];
            }

            $fiveStar = Score::where('bangumi_id', $bangumiId)
                ->whereNotNull('published_at')
                ->where('total', '>=', 80)
                ->count();

            $fourStar = Score::where('bangumi_id', $bangumiId)
                ->whereNotNull('published_at')
                ->where('total', '>=', 60)
                ->where('total', '<', 80)
                ->count();

            $threeStar = Score::where('bangumi_id', $bangumiId)
                ->whereNotNull('published_at')
                ->where('total', '>=', 40)
                ->where('total', '<', 60)
                ->count();

            $twoStar = Score::where('bangumi_id', $bangumiId)
                ->whereNotNull('published_at')
                ->where('total', '>=', 20)
                    ->where('total', '<', 40)
                    ->count();

            $oneStar = Score::where('bangumi_id', $bangumiId)
                ->whereNotNull('published_at')
                ->where('total', '<', 20)
                ->count();

            return [
                'total' => round($total / $count, 1),
                'count' => $count,
                'radar' => [
                    'lol' => round($lol / $count, 1),
                    'cry' => round($cry / $count, 1),
                    'fight' => round($fight / $count, 1),
                    'moe' => round($moe / $count, 1),
                    'sound' => round($sound / $count, 1),
                    'vision' => round($vision / $count, 1),
                    'role' => round($role / $count, 1),
                    'story' => round($story / $count, 1),
                    'express' => round($express / $count, 1),
                    'style' => round($style / $count, 1),
                ],
                'ladder' => [
                    [
                        'key' => 1,
                        'val' => $oneStar
                    ],
                    [
                        'key' => 2,
                        'val' => $twoStar
                    ],
                    [
                        'key' => 3,
                        'val' => $threeStar
                    ],
                    [
                        'key' => 4,
                        'val' => $fourStar
                    ],
                    [
                        'key' => 5,
                        'val' => $fiveStar
                    ]
                ]
            ];
        }, 1);
    }

    public function userScoreIds($userId, $page, $take)
    {
        $ids = $this->Cache($this->cacheKeyUserScoreIds($userId), function () use ($userId)
        {
            return Score::where('user_id', $userId)
                ->whereNotNull('published_at')
                ->orderBy('created_at', 'DESC')
                ->pluck('id')
                ->toArray();
        }, 'm');

        return $this->filterIdsByPage($ids, $page, $take);
    }

    public function doPublish($userId, $scoreId, $bangumiId)
    {
        $bangumiScoreService = new BangumiScoreService();
        $bangumiScoreService->do($userId, $bangumiId);
        Redis::DEL($this->cacheKeyBangumiScore($bangumiId));

        $scoreTrendingService = new ScoreTrendingService(0, $bangumiId);
        $scoreTrendingService->create($scoreId);

        $bangumiFollowService = new BangumiFollowService();
        if (!$bangumiFollowService->check($userId, $bangumiId))
        {
            // 如果没有关注，就给他关注
            $bangumiFollowService->do($userId, $bangumiId);
        }

        $job = (new \App\Jobs\Trial\JsonContent\TrialScore($scoreId));
        dispatch($job);

        $totalScoreCount = new TotalScoreCount();
        $totalScoreCount->add();
        // TODO：SEO
        // TODO：SEARCH
    }

    public function cacheKeyUserScoreIds($userId)
    {
        return 'user_' . $userId . '_score';
    }

    public function cacheKeyBangumiScore($bangumiId)
    {
        return 'bangumi_' . $bangumiId . '_score';
    }

    public function cacheKeyScoreItem($id)
    {
        return 'score_' . $id;
    }
}