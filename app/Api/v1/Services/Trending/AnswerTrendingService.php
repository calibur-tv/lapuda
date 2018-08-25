<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/23
 * Time: 下午10:39
 */

namespace App\Api\V1\Services\Trending;


use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Services\Comment\AnswerCommentService;
use App\Api\V1\Services\Toggle\Question\AnswerLikeService;
use App\Api\V1\Services\Toggle\Question\AnswerMarkService;
use App\Api\V1\Services\Toggle\Question\AnswerRewardService;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Transformers\AnswerTransformer;
use App\Models\Answer;

class AnswerTrendingService extends TrendingService
{
    protected $visitorId;
    protected $questionId;

    public function __construct($questionId = 0, $userId = 0)
    {
        parent::__construct('question_answers', $questionId, $userId, 'question');
    }

    public function computeNewsIds()
    {
        return
            Answer
                ::where('state', 0)
                ->when($this->bangumiId, function ($query)
                {
                    return $query
                        ->where('question_id', $this->bangumiId);
                })
                ->orderBy('created_at', 'desc')
                ->take(100)
                ->whereNotNull('published_at')
                ->pluck('id');
    }

    public function computeActiveIds()
    {
        return
            Answer
            ::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query
                    ->where('question_id', $this->bangumiId);
            })
            ->orderBy('updated_at', 'desc')
            ->take(100)
            ->whereNotNull('published_at')
            ->pluck('updated_at', 'id');
    }

    public function computeUserIds()
    {
        return
            Answer
            ::where('state', 0)
            ->where('user_id', $this->userId)
            ->orderBy('created_at', 'desc')
            ->pluck('id');
    }

    public function getListByIds($ids, $flowType)
    {
        $store = new AnswerRepository();
        if ($flowType === 'trending')
        {
            $list = $store->trendingFlow($ids);
        }
        else if ($flowType === 'user')
        {
            $list = $store->userFlow($ids);
        }
        else
        {
            $list = $store->questionFlow($ids, $this->userId);
        }
        if (empty($list))
        {
            return [];
        }

        $likeService = new AnswerLikeService();
        $rewardService = new AnswerRewardService();
        $markService = new AnswerMarkService();
        $commentService = new AnswerCommentService();

        foreach ($list as $i => $item)
        {
            if ($item['source_url'])
            {
                $list[$i]['like_users'] = $likeService->users($item['id']);
                $list[$i]['reward_users'] = [
                    'list' => [],
                    'total' => 0,
                    'noMore' => true
                ];
            }
            else
            {
                $list[$i]['reward_users'] = $rewardService->users($item['id']);
                $list[$i]['like_users'] = [
                    'list' => [],
                    'total' => 0,
                    'noMore' => true
                ];
            }

            $list[$i]['mark_users'] = $markService->users($item['id']);
        }

        $list = $commentService->batchGetCommentCount($list);
        $list = $rewardService->batchCheck($list, $this->userId, 'rewarded');
        $list = $likeService->batchCheck($list, $this->userId, 'liked');
        $list = $markService->batchCheck($list, $this->userId, 'marked');

        $transformer = new AnswerTransformer();
        if ($flowType === 'trending')
        {
            return $transformer->trendingFlow($list);
        }
        else if ($flowType === 'user')
        {
            return $transformer->userFlow($list);
        }

        return $transformer->questionFlow($list);
    }
}