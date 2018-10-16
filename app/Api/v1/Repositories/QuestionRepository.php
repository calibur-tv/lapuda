<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 下午2:27
 */

namespace App\Api\V1\Repositories;


use App\Api\V1\Services\Comment\QuestionCommentService;
use App\Api\V1\Services\Counter\QuestionAnswerCounter;
use App\Api\V1\Services\Counter\QuestionViewCounter;
use App\Api\V1\Services\Owner\QuestionLog;
use App\Api\V1\Services\Tag\QuestionTagService;
use App\Api\V1\Services\Toggle\Question\QuestionFollowService;
use App\Api\V1\Services\Trending\QuestionTrendingService;
use App\Api\V1\Services\UserLevel;
use App\Api\V1\Transformers\QuestionTransformer;
use App\Models\Answer;
use App\Models\Question;
use App\Services\BaiduSearch\BaiduPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

class QuestionRepository extends Repository
{
    public function create(Array $params)
    {
        $text = $params['text'];

        $content = [
            'text' => $this->formatRichContent($text),
            'images' => array_map(function ($image)
            {
                $image['url'] = $this->convertImagePath($image['url']);

                return $image;
            }, $params['images'])
        ];

        $now = Carbon::now();
        // TODO：去掉 title 前后的标点，并给后面补上中文的问号
        $title = Purifier::clean($params['title']);
        $newId = Question::insertGetId([
            'user_id' => $params['user_id'],
            'title' => $title,
            'intro' => mb_substr($text, 0, 120, 'UTF-8'),
            'content' => json_encode($content),
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // TODO：对 tags 进行合法性校验
        $questionTagService = new QuestionTagService();
        $questionTagService->update($newId, $params['tags']);

        $questionFollowService = new QuestionFollowService();
        $questionFollowService->do($params['user_id'], $newId);

        $questionLog = new QuestionLog();
        $questionLog->set($newId, $params['user_id'], true);

        $job = (new \App\Jobs\Trial\Question\Create($newId));
        dispatch($job);

        return $newId;
    }

    public function item($id, $isShow = false)
    {
        $result = $this->Cache($this->itemCacheKey($id), function () use ($id)
        {
            $question = Question
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($question))
            {
                return null;
            }

            $question = $question->toArray();

            $content = json_decode($question['content'], true);
            $question['content'] = $content['text'];
            $question['images'] = $content['images'];

            $question['tag_ids'] = DB
                ::table('question_tag_relations')
                ->where('model_id', $id)
                ->pluck('tag_id')
                ->toArray();
            $question['bangumi_id'] = $question['tag_ids'];

            return $question;
        });

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
        }

        return $result;
    }

    public function show($questionId, $userId)
    {
        $question = $this->item($questionId);

        $questionCommentService = new QuestionCommentService();
        $question['commented'] = $questionCommentService->checkCommented($userId, $questionId);
        $question['comment_count'] = $questionCommentService->getCommentCount($questionId);

        $questionFollowService = new QuestionFollowService();
        $question['follow_users'] = $questionFollowService->users($questionId);
        $question['followed'] = $questionFollowService->check($userId, $questionId);

        $questionTagService = new QuestionTagService();
        $question['tags'] = $questionTagService->tags($questionId);

        $questionViewCounter = new QuestionViewCounter();
        $question['view_count'] = $questionViewCounter->add($questionId);

        $questionAnswerCounter = new QuestionAnswerCounter();
        $question['answer_count'] = $questionAnswerCounter->get($questionId);
        $question['my_answer'] = $this->getMyAnswerMeta($questionId, $userId);

        $questionTransformer = new QuestionTransformer();

        return $questionTransformer->show($question);
    }

    public function checkHasAnswer($questionId, $userId)
    {
        if (!$userId)
        {
            return false;
        }

        return Answer
            ::where('user_id', $userId)
            ->where('question_id', $questionId)
            ->pluck('id')
            ->first();
    }

    public function getMyAnswerMeta($questionId, $userId)
    {
        if (!$userId)
        {
            return null;
        }

        return Answer
            ::where('user_id', $userId)
            ->where('question_id', $questionId)
            ->select('id', 'published_at')
            ->first();
    }

    public function publishAnswer($userId, $answerId, $questionId)
    {
        $questionFollowService = new QuestionFollowService();
        if (!$questionFollowService->check($userId, $questionId))
        {
            $questionFollowService->do($userId, $questionId);
        }

        $job = (new \App\Jobs\Trial\Answer\Create($answerId));
        dispatch($job);
    }

    public function createProcess($id, $state = 0)
    {
        $question = $this->item($id);

        if ($state)
        {
            DB::table('questions')
                ->where('id', $id)
                ->update([
                    'state' => $state
                ]);
        }

        $questionTrendingService = new QuestionTrendingService($question['tag_ids'], $question['user_id']);
        $questionTrendingService->create($id);

        $baiduPush = new BaiduPush();
        $baiduPush->trending('question');
        foreach ($question['tag_ids'] as $bangumiId)
        {
            $baiduPush->bangumi($bangumiId, 'qaq');
        }

        $this->migrateSearchIndex('C', $id, false);
    }

    public function updateProcess($id)
    {
        $this->migrateSearchIndex('U', $id, false);
    }

    public function deleteProcess($id, $state = 0)
    {
        $question = $this->item($id, true);

        DB::table('questions')
            ->where('id', $id)
            ->update([
                'state' => $state,
                'deleted_at' => Carbon::now()
            ]);

        if ($state === 0 || $question['created_at'] !== $question['updated_at'])
        {
            $questionTrendingService = new QuestionTrendingService($question['tag_ids'], $question['user_id']);
            $questionTrendingService->delete($id);

            $job = (new \App\Jobs\Search\Index('D', 'post', $id));
            dispatch($job);
        }

        $userLevel = new UserLevel();
        $exp = $userLevel->change($question['user_id'], -3, $question['intro']);

        Redis::DEL($this->itemCacheKey($id));

        return $exp;
    }

    public function recoverProcess($id)
    {
        $question = $this->item($id, true);

        if ($question['user_id'] == $question['state'])
        {
            DB::table('questions')
                ->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => null
                ]);

            $questionTrendingService = new QuestionTrendingService($question['tag_ids'], $question['user_id']);
            $questionTrendingService->create($id);

            $this->migrateSearchIndex('C', $id, false);

            Redis::DEL($this->itemCacheKey($id));
        }
        else
        {
            DB::table('questions')
                ->where('id', $id)
                ->update([
                    'state' => 0
                ]);
        }
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $question = $this->item($id);
        $content = $question['title'] . '|' . $question['intro'];

        $job = (new \App\Jobs\Search\Index($type, 'question', $id, $content));
        dispatch($job);
    }

    public function itemCacheKey($id)
    {
        return 'question_' . $id;
    }
}