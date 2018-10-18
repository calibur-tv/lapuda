<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午4:09
 */

namespace App\Api\V1\Repositories;


use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Bangumi\BangumiScoreService;
use App\Api\V1\Services\Toggle\Score\ScoreLikeService;
use App\Api\V1\Services\Toggle\Score\ScoreMarkService;
use App\Api\V1\Services\Toggle\Score\ScoreRewardService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
use App\Api\V1\Services\UserLevel;
use App\Models\Score;
use App\Services\BaiduSearch\BaiduPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ScoreRepository extends Repository
{
    public function item($id, $isShow = false)
    {
        $result = $this->Cache($this->itemCacheKey($id), function () use ($id)
        {
            $score = Score
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($score))
            {
                return null;
            }

            $score = $score->toArray();
            $score['content'] = $this->formatJsonContent($score['content']);
            $score['total'] = number_format($score['total'] / 10, 1);
            $score['lol'] = number_format($score['lol'] / 2, 1);
            $score['cry'] = number_format($score['cry'] / 2, 1);
            $score['fight'] = number_format($score['fight'] / 2, 1);
            $score['moe'] = number_format($score['moe'] / 2, 1);
            $score['sound'] = number_format($score['sound'] / 2, 1);
            $score['vision'] = number_format($score['vision'] / 2, 1);
            $score['role'] = number_format($score['role'] / 2, 1);
            $score['story'] = number_format($score['story'] / 2, 1);
            $score['express'] = number_format($score['express'] / 2, 1);
            $score['style'] = number_format($score['style'] / 2, 1);

            return $score;
        });

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
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
                'total' => number_format($total / $count, 0),
//                'total' => number_format($total / $count, 1), // 暂时修复小数点N位的Bug
                'count' => $count,
                'radar' => [
                    'lol' => number_format($lol / $count, 1),
                    'cry' => number_format($cry / $count, 1),
                    'fight' => number_format($fight / $count, 1),
                    'moe' => number_format($moe / $count, 1),
                    'sound' => number_format($sound / $count, 1),
                    'vision' => number_format($vision / $count, 1),
                    'role' => number_format($role / $count, 1),
                    'story' => number_format($story / $count, 1),
                    'express' => number_format($express / $count, 1),
                    'style' => number_format($style / $count, 1),
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

    public function doPublish($userId, $scoreId, $bangumiId)
    {
        $bangumiFollowService = new BangumiFollowService();
        if (!$bangumiFollowService->check($userId, $bangumiId))
        {
            // 如果没有关注，就给他关注
            $bangumiFollowService->do($userId, $bangumiId);
        }

        $job = (new \App\Jobs\Trial\Score\Create($scoreId));
        dispatch($job);
    }

    public function cacheKeyBangumiScore($bangumiId)
    {
        return 'bangumi_' . $bangumiId . '_score';
    }

    public function createProcess($id, $state = 0)
    {
        $score = $this->item($id, true);

        Redis::DEL($this->cacheKeyBangumiScore($score['bangumi_id']));

        if ($state)
        {
            DB::table('scores')
                ->where('id', $id)
                ->update([
                    'state' => $state
                ]);
        }

        if ($score['created_at'] == $score['updated_at'])
        {
            $bangumiScoreService = new BangumiScoreService();
            $bangumiScoreService->do($score['user_id'], $score['bangumi_id']);

            $scoreTrendingService = new ScoreTrendingService($score['bangumi_id'], $score['user_id']);
            $scoreTrendingService->create($id);

            $baiduPush = new BaiduPush();
            $baiduPush->trending('score');
            $baiduPush->bangumi($score['bangumi_id'], 'review');

            $this->migrateSearchIndex('C', $id, false);
        }
    }

    public function updateProcess($id)
    {
        $this->migrateSearchIndex('U', $id, false);
    }

    public function deleteProcess($id, $state = 0)
    {
        $score = $this->item($id, true);
        $userId = $score['user_id'];

        DB::table('scores')
            ->where('id', $id)
            ->update([
                'state' => $state,
                'deleted_at' => Carbon::now()
            ]);

        Redis::DEL($this->itemCacheKey($id));

        if ($state === 0 || $score['created_at'] !== $score['updated_at'])
        {
            $bangumiScoreService = new BangumiScoreService();
            $bangumiScoreService->undo($score['user_id'], $score['bangumi_id']);

            $scoreTrendingService = new ScoreTrendingService($score['bangumi_id'], $userId);
            $scoreTrendingService->delete($id);

            $job = (new \App\Jobs\Search\Index('D', 'score', $id));
            dispatch($job);
        }

        $scoreRewardService = new ScoreRewardService();
        $scoreRewardService->cancel($id);

        $userLevel = new UserLevel();
        $exp = $userLevel->change($score['user_id'], -5, $score['intro']);
        if ($score['is_creator'])
        {
            $total = $scoreRewardService->total($id);
            $cancelEXP1 = $total * -3;
            $exp += $cancelEXP1;
        }
        else
        {
            $scoreLikeService = new ScoreLikeService();
            $total = $scoreLikeService->total($id);
            $cancelEXP1 = $total * -2;
            $exp += $cancelEXP1;
        }
        $scoreMarkService = new ScoreMarkService();
        $total = $scoreMarkService->total($id);
        $cancelEXP2 = $total * -2;
        $exp += $cancelEXP2;
        $userLevel->change($userId, $cancelEXP1 + $cancelEXP2, false);

        return $exp;
    }

    public function recoverProcess($id)
    {
        $score = $this->item($id, true);

        if ($score['user_id'] == $score['state'])
        {
            DB::table('scores')
                ->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => null
                ]);

            $scoreTrendingService = new ScoreTrendingService($score['bangumi_id'], $score['user_id']);
            $scoreTrendingService->create($id);

            $this->migrateSearchIndex('C', $id, false);

            Redis::DEL($this->itemCacheKey($id));
        }
        else
        {
            DB::table('scores')
                ->where('id', $id)
                ->update([
                    'state' => 0
                ]);
        }
    }

    public function itemCacheKey($id)
    {
        return 'score_' . $id;
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $score = $this->item($id);
        $content = $score['title'] . '|' . $score['intro'];

        $job = (new \App\Jobs\Search\Index($type, 'score', $id, $content));
        dispatch($job);
    }
}