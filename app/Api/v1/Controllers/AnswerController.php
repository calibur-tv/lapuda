<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/23
 * Time: 下午11:09
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Services\Trending\AnswerTrendingService;
use App\Models\Answer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

class AnswerController extends Controller
{
    public function show($id)
    {
        $answerRepository = new AnswerRepository();
        $answer = $answerRepository->item($id, true);
        if (is_null($answer))
        {
            return $this->resErrNotFound();
        }

        $isDeleted = false;
        if ($answer['deleted_at'])
        {
            if ($answer['state'])
            {
                return $this->resErrLocked();
            }

            $isDeleted = true;
        }

        $questionRepository = new QuestionRepository();
        $questionId = $answer['question_id'];
        $question = $questionRepository->item($questionId);

        if (is_null($question))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        $question = $questionRepository->show($questionId, $userId);

        if (!$isDeleted)
        {
            $answerTrendingService = new AnswerTrendingService($questionId, $userId);
            $answer = $answerTrendingService->getListByIds([$id], '')[0];
        }

        return $this->resOK([
            'question' => $question,
            'answer' => $isDeleted ? null : $answer
        ]);
    }

    public function editData($id)
    {
        $answerRepository = new AnswerRepository();
        $answer = $answerRepository->item($id);
        if (is_null($answer))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        if ($userId !== $answer['user_id'])
        {
            return $this->resErrRole();
        }

        return $this->resOK($answer);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|integer',
            'intro' => 'required|max:120',
            'content' => 'required|Array',
            'do_publish' => 'required|boolean',
            'source_url' => 'present|URL'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();
        $questionId = $request->get('question_id');
        $questionRepository = new QuestionRepository();
        $question = $questionRepository->item($questionId);
        if (is_null($question))
        {
            return $this->resErrBad('问题不存在或已被删除');
        }

        if ($questionRepository->checkHasAnswer($questionId, $userId))
        {
            return $this->resErrRole('不能重复作答');
        }

        $now = Carbon::now();
        $doPublished = $request->get('do_publish');
        $newId = Answer::insertGetId([
            'user_id' => $userId,
            'question_id' => $questionId,
            'content' => $questionRepository->filterJsonContent($request->get('content')),
            'intro' => Purifier::clean($request->get('intro')),
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $doPublished ? $now : null,
            'source_url' => $request->get('source_url')
        ]);

        if ($doPublished)
        {
            $questionRepository->publishAnswer($userId, $newId, $questionId);
        }

        return $this->resOK($newId);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'intro' => 'required|max:120',
            'content' => 'required|Array',
            'do_publish' => 'required|boolean',
            'source_url' => 'present|URL'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $answerRepository = new AnswerRepository();
        $answer = $answerRepository->item($id);
        if (is_null($answer))
        {
            return $this->resErrNotFound('不存在的答案');
        }

        $userId = $this->getAuthUserId();
        if ($userId !== $answer['user_id'])
        {
            return $this->resErrRole();
        }

        $questionRepository = new QuestionRepository();
        $question = $questionRepository->item($answer['question_id']);
        if (is_null($question))
        {
            return $this->resErrBad('问题不存在或已被删除');
        }

        $now = Carbon::now();
        $doPublished = $request->get('do_publish');
        $sourceUrl = $request->get('source_url');
        if (!$sourceUrl && $answer['source_url'])
        {
            $sourceUrl = $answer['source_url'];
        }

        Answer
            ::where('id', $id)
            ->update([
                'content' => $questionRepository->filterJsonContent($request->get('content')),
                'intro' => Purifier::clean($request->get('intro')),
                'updated_at' => $now,
                'published_at' => $doPublished ? $now : null,
                'source_url' => $sourceUrl
            ]);

        if ($doPublished)
        {
            $questionRepository->publishAnswer($userId, $id, $answer['question_id']);
        }

        Redis::DEL($answerRepository->itemCacheKey($id));

        return $this->resNoContent();
    }

    public function delete($id)
    {
        $answerRepository = new AnswerRepository();
        $answer = $answerRepository->item($id);
        if (is_null($answer))
        {
            return $this->resErrNotFound();
        }
        if ($answer['user_id'] !== $this->getAuthUserId())
        {
            return $this->resErrRole();
        }

        $answerRepository->deleteProcess($id);

        return $this->resNoContent();
    }

    public function trials()
    {

    }

    public function ban()
    {

    }

    public function pass()
    {

    }
}