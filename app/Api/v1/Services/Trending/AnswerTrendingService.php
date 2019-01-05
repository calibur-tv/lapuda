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
use App\Api\V1\Services\Comment\QuestionCommentService;
use App\Api\V1\Services\Counter\QuestionAnswerCounter;
use App\Api\V1\Services\Toggle\Question\AnswerLikeService;
use App\Api\V1\Services\Toggle\Question\AnswerMarkService;
use App\Api\V1\Services\Toggle\Question\AnswerRewardService;
use App\Api\V1\Services\Toggle\Question\QuestionFollowService;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Services\Vote\AnswerVoteService;
use App\Api\V1\Transformers\AnswerTransformer;
use App\Api\V1\Transformers\QuestionTransformer;
use App\Models\Answer;
use Carbon\Carbon;

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
                ::when($this->bangumiId, function ($query)
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
                ::when($this->bangumiId, function ($query)
                {
                    return $query
                        ->where('question_id', $this->bangumiId);
                })
                ->orderBy('updated_at', 'desc')
                ->take(100)
                ->whereNotNull('published_at')
                ->pluck('updated_at', 'id');
    }

    public function computeHotIds()
    {
        $ids = Answer
            ::when($this->bangumiId, function ($query)
            {
                return $query
                    ->where('question_id', $this->bangumiId);
            })
            ->whereNotNull('published_at')
            ->pluck('id');

        $answerRepository = new AnswerRepository();
        $answerLikeService = new AnswerLikeService();
        $answerVoteCounter = new AnswerVoteService();
        $answerMarkService = new AnswerMarkService();
        $answerRewardService = new AnswerRewardService();
        $answerCommentService = new AnswerCommentService();

        $list = $answerRepository->list($ids);

        $result = [];
        // https://segmentfault.com/a/1190000004253816
        foreach ($list as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $answerId = $item['id'];
            $likeCount = $answerLikeService->total($answerId);
            $markCount = $answerMarkService->total($answerId);
            $rewardCount = $answerRewardService->total($answerId);
            $voteCount = $answerVoteCounter->getVoteCount($answerId);
            $commentCount = $answerCommentService->getCommentCount($answerId);

            $result[$answerId] = (
//                    ($voteCount && log($voteCount, 10) * 4) +
                    $voteCount +
                    ($likeCount * 2 + $markCount * 2 + $rewardCount * 3) +
                    ($commentCount && log($commentCount, M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.1);
        }

        return $result;
    }

    public function computeUserIds()
    {
        return
            Answer
            ::where('user_id', $this->userId)
            ->orderBy('created_at', 'desc')
            ->whereNotNull('published_at')
            ->pluck('id');
    }

    public function getListByIds($ids, $flowType)
    {
        $store = new AnswerRepository();
        $qaqFlow = $flowType !== 'trending' && $flowType !== 'user';
        if ($qaqFlow)
        {
            $list = $store->questionFlow($ids, $this->userId);
        }
        else
        {
            $list = $store->trendingFlow($ids);
        }
        if (empty($list))
        {
            return [];
        }

        if ($qaqFlow)
        {
            $likeService = new AnswerLikeService();
            $rewardService = new AnswerRewardService();
            $markService = new AnswerMarkService();
            $commentService = new AnswerCommentService();

            foreach ($list as $i => $item)
            {
                if ($item['source_url'])
                {
                    $list[$i]['reward_users'] = [
                        'list' => [],
                        'total' => 0,
                        'noMore' => true
                    ];
                }
                else
                {
                    $list[$i]['reward_users'] = $rewardService->users($item['id']);
                }

                $list[$i]['like_users'] = $likeService->users($item['id']);
                $list[$i]['mark_users'] = $markService->users($item['id']);
            }

            $list = $commentService->batchGetCommentCount($list);
            $list = $rewardService->batchCheck($list, $this->userId, 'rewarded');
            $list = $likeService->batchCheck($list, $this->userId, 'liked');
            $list = $markService->batchCheck($list, $this->userId, 'marked');
        }
        else
        {
            $questionAnswerCounter = new QuestionAnswerCounter();
            $questionFollowService = new QuestionFollowService();
            $questionCommentService = new QuestionCommentService();

            $list = $questionAnswerCounter->batchGet($list, 'answer_count');
            $list = $questionFollowService->batchTotal($list, 'follow_count');
            $list = $questionCommentService->batchGetCommentCount($list);
        }

        if ($qaqFlow)
        {
            $answerTransformer = new AnswerTransformer();
            return $answerTransformer->questionFlow($list);
        }

        $questionTransformer = new QuestionTransformer();
        return $questionTransformer->trendingFlow($list);
    }
}