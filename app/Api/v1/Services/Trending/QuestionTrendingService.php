<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 下午4:20
 */

namespace App\Api\V1\Services\Trending;


use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Services\Comment\AnswerCommentService;
use App\Api\V1\Services\Comment\QuestionCommentService;
use App\Api\V1\Services\Counter\QuestionAnswerCounter;
use App\Api\V1\Services\Counter\QuestionViewCounter;
use App\Api\V1\Services\Toggle\Question\QuestionFollowService;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Services\Vote\AnswerVoteService;
use App\Api\V1\Transformers\QuestionTransformer;
use App\Models\Question;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QuestionTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($bangumiId = 0, $userId = 0)
    {
        parent::__construct('questions', $bangumiId, $userId);
    }

    public function computeActiveIds()
    {
        return
            DB
                ::table('questions AS qaq')
                ->where('qaq.state', 0)
                ->when($this->bangumiId, function ($query)
                {
                    return $query
                        ->leftJoin('question_tag_relations AS tag', 'tag.model_id', '=', 'qaq.id')
                        ->where('tag.tag_id', $this->bangumiId);
                })
                ->whereNull('deleted_at')
                ->orderBy('qaq.updated_at', 'desc')
                ->take(100)
                ->pluck('qaq.updated_at', 'qaq.id');
    }

    public function computeUserIds()
    {
        return
            Question
                ::where('user_id', $this->userId)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->pluck('id');
    }

    public function computeHotIds()
    {
        $ids = DB
            ::table('questions AS qaq')
            ->where('qaq.state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query
                    ->leftJoin('question_tag_relations AS tag', 'tag.model_id', '=', 'qaq.id')
                    ->where('tag.tag_id', $this->bangumiId);
            })
            ->whereNull('deleted_at')
            ->where('created_at', '>', Carbon::now()->addDays(-30))
            ->pluck('qaq.id');

        $questionRepository = new QuestionRepository();
        $questionFollowCount = new QuestionFollowService();
        $qaqAnswerCount = new QuestionAnswerCounter();
        $questionViewCounter = new QuestionViewCounter();
        $questionCommentService = new QuestionCommentService();

        $list = $questionRepository->list($ids);

        $result = [];
        // https://segmentfault.com/a/1190000004253816
        foreach ($list as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $questionId = $item['id'];
            $followCount = $questionFollowCount->total($questionId);
            $answerCount = $qaqAnswerCount->get($questionId);
            $viewCount = $questionViewCounter->get($questionId);
            $commentCount = $questionCommentService->getCommentCount($questionId);

            $result[$questionId] = (
                    ($viewCount && log($viewCount, 10) * 4) +
                    ($followCount * 2 + $answerCount * 2) +
                    ($commentCount && log($commentCount, M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.1);
        }

        return $result;
    }

    public function getListByIds($ids, $flowType)
    {
        $questionRepository = new QuestionRepository();
        $list = $questionRepository->bangumiFlow($ids);
        if (empty($list))
        {
            return [];
        }

        $questionAnswerCounter = new QuestionAnswerCounter();
        $questionFollowService = new QuestionFollowService();
        $questionCommentService = new QuestionCommentService();

        $list = $questionAnswerCounter->batchGet($list, 'answer_count');
        $list = $questionFollowService->batchTotal($list, 'follow_count');
        $list = $questionCommentService->batchGetCommentCount($list);

        if ($flowType !== 'user')
        {
            $answerRepository = new AnswerRepository();
            $answerVoteService = new AnswerVoteService();

            foreach ($list as $i => $item)
            {
                $idsObj = $this->getQAQNewAnswerIds($item['id']);

                if (!$idsObj['total'])
                {
                    $list[$i]['answer'] = null;
                    continue;
                }
                $answer = $answerRepository->item($idsObj['ids'][0]);
                if (is_null($answer))
                {
                    $list[$i]['answer'] = null;
                    continue;
                }

                $answer['vote_count'] = $answerVoteService->getVoteCount($answer['id']);

                $list[$i]['answer'] = $answer;
            }
        }

        $questionTransformer = new QuestionTransformer();
        if ($flowType === 'user')
        {
            return $questionTransformer->userFlow($list);
        }

        return $questionTransformer->trendingFlow($list);
    }

    protected function getQAQNewAnswerIds($questionId)
    {
        $answerTrendingService = new AnswerTrendingService($questionId);

        return $answerTrendingService->getNewsIds(0, 1);
    }
}