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
use App\Api\V1\Transformers\ScoreTransformer;
use App\Models\Score;

class ScoreTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($visitorId = 0, $bangumiId = 0)
    {
        parent::__construct('scores', $bangumiId);

        $this->visitorId = $visitorId;
        $this->bangumiId = $bangumiId;
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
            ->latest()
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
            ->latest()
            ->take(100)
            ->pluck('updated_at', 'id');
    }

    public function computeHotIds()
    {
        return [];
    }

    protected function getListByIds($ids)
    {
        $scoreRepository = new ScoreRepository();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();

        $scores = $scoreRepository->list($ids);
        $result = [];

        foreach ($scores as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $user = $userRepository->item($item['user_id']);
            if (is_null($user))
            {
                continue;
            }

            $bangumi = $bangumiRepository->item($item['bangumi_id']);
            if (is_null($bangumi))
            {
                continue;
            }

            $item['user'] = $user;
            $item['bangumi'] = $bangumi;
            $item['liked'] = false;
            $item['commented'] = false;

            $result[] = $item;
        }

        if (empty($result))
        {
            return [];
        }

        $scoreLikeService = new ScoreLikeService();
        $scoreCommentService = new ScoreCommentService();
        $scoreTransformer = new ScoreTransformer();

//        $result = $scoreLikeService->batchCheck($result, $this->visitorId, 'liked');
        $result = $scoreLikeService->batchTotal($result, 'like_count');
//        $result = $scoreCommentService->batchCheckCommented($result, $this->visitorId);
        $result = $scoreCommentService->batchGetCommentCount($result);

        return $scoreTransformer->trending($result);
    }
}