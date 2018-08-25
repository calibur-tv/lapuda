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
                        ->leftJoin('question_tag_relations AS tag', 'tag.tag_id', '=', $this->bangumiId);
                })
                ->orderBy('qaq.updated_at', 'desc')
                ->take(100)
                ->pluck('qaq.updated_at', 'qaq.id');
    }

    public function computeUserIds()
    {
        return
            Question
                ::where('state', 0)
                ->where('user_id', $this->userId)
                ->orderBy('created_at', 'desc')
                ->pluck('id');
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
                $answerVoteService = new AnswerVoteService();
                $answerCommentService = new AnswerCommentService();

                $answer['vote_count'] = $answerVoteService->getVoteCount($answer['id']);
                $answer['comment_count'] = $answerCommentService->getCommentCount($answer['id']);

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