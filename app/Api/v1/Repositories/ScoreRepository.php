<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午4:09
 */

namespace App\Api\V1\Repositories;


use App\Models\Score;

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

            return $score->toArray();
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
                ->where('total', '>=', 80)
                ->count();

            $fourStar = Score::where('bangumi_id', $bangumiId)
                ->where('total', '>=', 60)
                ->where('total', '<', 80)
                ->count();

            $threeStar = Score::where('bangumi_id', $bangumiId)
                ->where('total', '>=', 40)
                ->where('total', '<', 60)
                ->count();

            $twoStar = Score::where('bangumi_id', $bangumiId)
                    ->where('total', '>=', 20)
                    ->where('total', '<', 40)
                    ->count();

            $oneStar = Score::where('bangumi_id', $bangumiId)
                ->where('total', '<', 20)
                ->count();

            return [
                'total' => $total / $count,
                'count' => $count,
                'radar' => [
                    'lol' => $lol / $count,
                    'cry' => $cry / $count,
                    'fight' => $fight / $count,
                    'moe' => $moe / $count,
                    'sound' => $sound / $count,
                    'vision' => $vision / $count,
                    'role' => $role / $count,
                    'story' => $story / $count,
                    'express' => $express / $count,
                    'style' => $style / $count,
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
                ->orderBy('created_at', 'DESC')
                ->pluck('id')
                ->toArray();
        }, 'm');

        return $this->filterIdsByPage($ids, $page, $take);
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