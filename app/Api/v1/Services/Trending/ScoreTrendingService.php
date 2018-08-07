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
use App\Api\V1\Services\Toggle\Score\ScoreLikeService;
use App\Api\V1\Services\Toggle\Score\ScoreMarkService;
use App\Api\V1\Services\Toggle\Score\ScoreRewardService;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Transformers\ScoreTransformer;
use App\Models\Score;

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
        return Score::where('state', 0)
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
        return Score::where('state', 0)
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
        return [];
    }

    public function computeUserIds()
    {
        return Score
            ::where('state', 0)
            ->where('user_id', $this->userId)
            ->orderBy('created_at', 'desc')
            ->whereNotNull('published_at')
            ->pluck('id');
    }

    protected function getListByIds($ids)
    {
        $store = new ScoreRepository();
        if ($this->bangumiId)
        {
            $list = $store->bangumiFlow($ids);
        }
        else if ($this->userId)
        {
            $list = $store->userFlow($ids);
        }
        else
        {
            $list = $store->trendingFlow($ids);
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
        if ($this->bangumiId)
        {
            return $transformer->bangumiFlow($list);
        }
        else if ($this->userId)
        {
            return $transformer->userFlow($list);
        }
        return $transformer->trendingFlow($list);
    }
}