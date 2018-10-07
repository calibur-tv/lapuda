<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/23
 * Time: 下午10:39
 */

namespace App\Api\V1\Repositories;


use App\Api\V1\Services\Toggle\Question\AnswerRewardService;
use App\Api\V1\Services\Trending\AnswerTrendingService;
use App\Api\V1\Services\Trending\QuestionTrendingService;
use App\Api\V1\Services\UserLevel;
use App\Api\V1\Services\Vote\AnswerVoteService;
use App\Models\Answer;
use App\Models\User;
use App\Services\BaiduSearch\BaiduPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AnswerRepository extends Repository
{
    public function item($id, $isShow = false)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->Cache($this->itemCacheKey($id), function () use ($id)
        {
            $answer = Answer
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($answer))
            {
                return null;
            }

            $answer = $answer->toArray();
            $answer['content'] = $this->formatJsonContent($answer['content']);
            $answer['is_creator'] = !$answer['source_url'];

            return $answer;
        });

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
        }

        return $result;
    }

    public function createProcess($id, $state = 0)
    {
        $answer = $this->item($id);

        if ($state)
        {
            DB
                ::table('question_answers')
                ->where('id', $id)
                ->update([
                    'state' => $state
                ]);
        }

        if ($answer['created_at'] === $answer['updated_at'])
        {
            $questionId = $answer['question_id'];
            $answerTrendingService = new AnswerTrendingService();
            $answerTrendingService->create($questionId, $answer['user_id']);

            $questionRepository = new QuestionRepository();
            $question = $questionRepository->item($questionId);

            $questionTrendingService = new QuestionTrendingService($question['tag_ids']);
            $questionTrendingService->update($questionId);

            DB::table('questions')
                ->where('id', $questionId)
                ->update([
                    'updated_at' => Carbon::now()
                ]);

            $job = (new \App\Jobs\Notification\Create(
                'question-answer',
                $question['user_id'],
                $answer['user_id'],
                $id
            ));
            dispatch($job);

            $userLevel = new UserLevel();
            $userLevel->change($answer['user_id'], 4);

            $baiduPush = new BaiduPush();
            $baiduPush->trending('question');

            $this->migrateSearchIndex('C', $id, false);
        }
    }

    public function updateProcess($id)
    {
        $this->migrateSearchIndex('U', $id, false);
    }

    public function deleteProcess($id, $state = 0)
    {
        $answer = $this->item($id, true);

        DB::table('question_answers')
            ->where('id', $id)
            ->update([
                'state' => $state,
                'deleted_at' => Carbon::now()
            ]);

        if ($state === 0 || $answer['created_at'] !== $answer['updated_at'])
        {
            $userLevel = new UserLevel();
            $userLevel->change($answer['user_id'], -4);

            $job = (new \App\Jobs\Search\Index('D', 'answer', $id));
            dispatch($job);
        }

        $answerRewardService = new AnswerRewardService();
        $answerRewardService->cancel($id);

        Redis::DEL($this->itemCacheKey($id));
    }

    public function recoverProcess($id)
    {
        $answer = $this->item($id, true);

        DB::table('question_answers')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => null
            ]);

        if ($answer['deleted_at'])
        {
            $this->migrateSearchIndex('C', $id, false);
        }

        Redis::DEL($this->itemCacheKey($id));
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $answer = $this->item($id);
        $content = $answer['intro'];

        $job = (new \App\Jobs\Search\Index($type, 'answer', $id, $content));
        dispatch($job);
    }

    public function itemCacheKey($id)
    {
        return 'answer_' . $id;
    }

    public function questionFlow($ids, $userId)
    {
        $list = $this->list($ids);

        $userRepository = new UserRepository();
        $answerVoteService = new AnswerVoteService();

        $list = $answerVoteService->batchVote($list);
        $list = $answerVoteService->batchCheck($list, $userId);

        return $userRepository->appendUserToList($list);
    }

    public function trendingFlow($ids)
    {
        $list = $this->list($ids);
        $userRepository = new UserRepository();
        $questionRepository = new QuestionRepository();
        $answerVoteService = new AnswerVoteService();

        foreach ($list as $i => $item)
        {
            $question = $questionRepository->item($item['question_id']);
            if (is_null($question))
            {
                continue;
            }
            $item['vote_count'] = $answerVoteService->getVoteCount($item['id']);
            $question['answer'] = $item;
            $list[$i] = $question;
        }
        $list = $userRepository->appendUserToList($list);

        return $list;
    }
}