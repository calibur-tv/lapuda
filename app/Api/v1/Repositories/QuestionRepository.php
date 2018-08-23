<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 下午2:27
 */

namespace App\Api\V1\Repositories;


use App\Api\V1\Services\Owner\QuestionLog;
use App\Api\V1\Services\Tag\QuestionTagService;
use App\Api\V1\Services\Toggle\Question\QuestionFollowService;
use App\Api\V1\Services\Trending\QuestionTrendingService;
use App\Models\Question;
use App\Services\BaiduSearch\BaiduPush;
use App\Services\OpenSearch\Search;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

class QuestionRepository extends Repository
{
    public function create(Array $params)
    {
        $content = [
            'text' => Purifier::clean($params['text']),
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
            'intro' => Purifier::clean($params['intro']),
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

            return $question;
        });

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
        }

        return $result;
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

        Redis::DEL($this->itemCacheKey($id));
    }

    public function recoverProcess($id)
    {
        $question = $this->item($id, true);

        DB::table('questions')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => null
            ]);

        if ($question['deleted_at'])
        {
            $questionTrendingService = new QuestionTrendingService($question['tag_ids'], $question['user_id']);
            $questionTrendingService->create($id);

            $this->migrateSearchIndex('C', $id, false);
        }

        Redis::DEL($this->itemCacheKey($id));
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $question = $this->item($id);
        $content = $question['title'] . '|' . $question['intro'];

        if ($async)
        {
            $job = (new \App\Jobs\Search\Index($type, 'question', $id, $content));
            dispatch($job);
        }
        else
        {
            $search = new Search();
            $search->create($id, $content, 'question');
            $baiduPush = new BaiduPush();
            $baiduPush->create($id, 'question');
        }
    }

    public function itemCacheKey($id)
    {
        return 'question_' . $id;
    }
}