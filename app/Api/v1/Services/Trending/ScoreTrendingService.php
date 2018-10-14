<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午4:48
 */

namespace App\Api\V1\Services\Trending;


use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\ScoreCommentService;
use App\Api\V1\Services\Counter\ScoreViewCounter;
use App\Api\V1\Services\Toggle\Score\ScoreLikeService;
use App\Api\V1\Services\Toggle\Score\ScoreMarkService;
use App\Api\V1\Services\Toggle\Score\ScoreRewardService;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Transformers\ScoreTransformer;
use App\Models\Score;
use Carbon\Carbon;

class ScoreTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($bangumiId = 0, $userId = 0)
    {
        parent::__construct('scores', $bangumiId, $userId);
    }

    public function computeNewsIds()
    {
        return Score
            ::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query->where('bangumi_id', $this->bangumiId);
            })
            ->orderBy('created_at', 'desc')
            ->whereNotNull('published_at')
            ->take(100)
            ->pluck('id');
    }

    public function computeActiveIds()
    {
        return Score
            ::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query->where('bangumi_id', $this->bangumiId);
            })
            ->orderBy('updated_at', 'desc')
            ->whereNotNull('published_at')
            ->take(100)
            ->pluck('updated_at', 'id');
    }

    public function computeHotIds()
    {
        $ids = Score
            ::where('state', 0)
            ->where('created_at', '>', Carbon::now()->addDays(-100))
            ->when($this->bangumiId, function ($query)
            {
                return $query->where('bangumi_id', $this->bangumiId);
            })
            ->pluck('id');

        $scoreRepository = new ScoreRepository();
        $scoreLikeService = new ScoreLikeService();
        $scoreMarkService = new ScoreMarkService();
        $scoreRewardService = new ScoreRewardService();
        $scoreViewCounter = new ScoreViewCounter();
        $scoreCommentService = new ScoreCommentService();

        $list = $scoreRepository->list($ids);

        $result = [];
        // https://segmentfault.com/a/1190000004253816
        foreach ($list as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $scoreId = $item['id'];
            $likeCount = $scoreLikeService->total($scoreId);
            $markCount = $scoreMarkService->total($scoreId);
            $rewardCount = $scoreRewardService->total($scoreId);
            $viewCount = $scoreViewCounter->get($scoreId);
            $commentCount = $scoreCommentService->getCommentCount($scoreId);

            $result[$scoreId] = (
                    ($viewCount && log($viewCount, 10) * 4) +
                    ($likeCount * 2 + $markCount * 2 + $rewardCount * 3) +
                    ($commentCount && log($commentCount, M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.3);
        }

        return $result;
    }

    public function computeUserIds()
    {
        return Score
            ::where('user_id', $this->userId)
            ->orderBy('created_at', 'desc')
            ->whereNotNull('published_at')
            ->pluck('id');
    }

    public function getListByIds($ids, $flowType)
    {
        $store = new ScoreRepository();
        if ($flowType === 'bangumi')
        {
            $list = $store->bangumiFlow($ids);
        }
        else if ($flowType === 'user')
        {
            $list = $store->userFlow($ids);
        }
        else
        {
            $list = $store->trendingFlow($ids);
        }
        if (empty($list))
        {
            return [];
        }

        $likeService = new ScoreLikeService();
        $rewardService = new ScoreRewardService();
        $markService = new ScoreMarkService();
        $commentService = new ScoreCommentService();

        $list = $commentService->batchGetCommentCount($list);
        $list = $likeService->batchTotal($list, 'like_count');
        $list = $markService->batchTotal($list, 'mark_count');
        foreach ($list as $i => $item)
        {
            if ($item['is_creator'])
            {
                $list[$i]['like_count'] = 0;
                $list[$i]['reward_count'] = $rewardService->total($item['id']);
            }
            else
            {
                $list[$i]['like_count'] = $likeService->total($item['id']);
                $list[$i]['reward_count'] = 0;
            }
        }

        $transformer = new ScoreTransformer();
        if ($flowType === 'bangumi')
        {
            return $transformer->bangumiFlow($list);
        }
        else if ($flowType === 'user')
        {
            return $transformer->userFlow($list);
        }

        return $transformer->trendingFlow($list);
    }
}