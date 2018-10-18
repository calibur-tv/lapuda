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
use App\Api\V1\Services\UserLevel;
use App\Api\V1\Transformers\AnswerTransformer;
use App\Models\Answer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("回答相关接口")
 */
class AnswerController extends Controller
{
    /**
     * 获取回答详情
     *
     * @Get("/question/soga/{id}/show")
     *
     * @Transaction({
     *      @Response(423, body={"code": 42301, "message": "内容正在审核中"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的漫评"}),
     *      @Response(200, body="详情")
     * })
     */
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

    /**
     * 编辑回答时，根据 id 获取数据
     *
     * @Get("/question/soga/{id}/resource")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的回答"}),
     *      @Response(403, body={"code": 40301, "message": "没有操作权限"}),
     *      @Response(200, body="回答数据")
     * })
     */
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

    /**
     * 创建回答
     *
     * @Post("/question/soga/{id}/create")
     *
     * @Parameters({
     *      @Parameter("question_id", description="问题的id", type="integer", required=true),
     *      @Parameter("intro", description="纯文本简介，120字以内", type="string", required=true),
     *      @Parameter("content", description="JSON-content 的内容", type="array", required=true),
     *      @Parameter("do_publish", description="是否公开发布", type="boolean", required=true),
     *      @Parameter("source_url", description="内容出处的 url", type="string"),
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的问题"}),
     *      @Response(403, body={"code": 40301, "message": "不能重复作答"}),
     *      @Response(400, body={"code": 40001, "message": "请求参数错误"}),
     *      @Response(200, body="回答的id")
     * })
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|integer',
            'intro' => 'present|max:120',
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
        $intro = Purifier::clean($request->get('intro'));
        $newId = Answer::insertGetId([
            'user_id' => $userId,
            'question_id' => $questionId,
            'content' => $questionRepository->filterJsonContent($request->get('content')),
            'intro' => $intro,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $doPublished ? $now : null,
            'source_url' => $request->get('source_url')
        ]);

        if ($doPublished)
        {
            $questionRepository->publishAnswer($userId, $newId, $questionId);
        }
        $userLevel = new UserLevel();
        $exp = $userLevel->change($userId, 4, $intro);

        return $this->resOK([
            'data' => $newId,
            'exp' => $exp,
            'message' => $doPublished ? (
                $exp ? "发布成功，经验+{$exp}" : "发布成功"
            ) : (
                $exp ? "保存成功，经验+{$exp}" : "保存成功"
            )
        ]);
    }

    /**
     * 更新自己的回答
     *
     * @Post("/question/soga/{id}/update")
     *
     * @Parameters({
     *      @Parameter("intro", description="纯文本简介，120字以内", type="string", required=true),
     *      @Parameter("content", description="JSON-content 的内容", type="array", required=true),
     *      @Parameter("do_publish", description="是否公开发布", type="boolean", required=true),
     *      @Parameter("source_url", description="内容出处的 url", type="string"),
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的答案|问题"}),
     *      @Response(403, body={"code": 40301, "message": "没有操作权限"}),
     *      @Response(400, body={"code": 40001, "message": "请求参数错误"}),
     *      @Response(204)
     * })
     */
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

    /**
     * 删除自己的回答
     *
     * @Post("/question/soga/{id}/delete")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(404, body={"code": 40401, "message": "数据不存在"}),
     *      @Response(403, body={"code": 40301, "message": "没有操作权限"}),
     *      @Response(204)
     * })
     */
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

        $exp = $answerRepository->deleteProcess($id);

        return $this->resOK([
            'exp' => $exp,
            'message' => $exp ? "删除成功，经验{$exp}" : "删除成功"
        ]);
    }

    /**
     * 获取用户的回答草稿列表
     *
     * @Get("/question/soga/drafts")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body="回答草稿列表")
     * })
     */
    public function drafts()
    {
        $userId = $this->getAuthUserId();
        $ids = Answer
            ::where('user_id', $userId)
            ->whereNull('published_at')
            ->orderBy('updated_at', 'DESC')
            ->pluck('id')
            ->toArray();

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $answerRepository = new AnswerRepository();
        $questionRepository = new QuestionRepository();

        $list = $answerRepository->list($ids);
        foreach ($list as $i => $item)
        {
            $question = $questionRepository->item($item['question_id']);
            if (is_null($question))
            {
                continue;
            }
            $list[$i]['question'] = $question;
        }

        $answerTransformer = new AnswerTransformer();

        return $this->resOK($answerTransformer->drafts($list));
    }

    // 后台回答待审列表
    public function trials()
    {
        $ids = Answer
            ::withTrashed()
            ->where('state', '<>', 0)
            ->pluck('id')
            ->toArray();

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $answerRepository = new AnswerRepository();
        $list = $answerRepository->list($ids, true);

        return $this->resOK($list);
    }

    // 后台删除回答
    public function ban(Request $request)
    {
        $id = $request->get('id');

        $answerRepository = new AnswerRepository();
        $answerRepository->deleteProcess($id);

        return $this->resNoContent();
    }

    // 后台通过回答
    public function pass(Request $request)
    {
        $id = $request->get('id');

        $answerRepository = new AnswerRepository();
        $answerRepository->recoverProcess($id);

        return $this->resNoContent();
    }

    // 后台确认删除
    public function approve(Request $request)
    {
        $id = $request->get('id');

        DB
            ::table('question_answers')
            ->where('id', $id)
            ->update([
                'state' => 0
            ]);

        Redis::DEL('answer_' . $id);

        return $this->resNoContent();
    }

    // 后台驳回删除
    public function reject(Request $request)
    {
        $id = $request->get('id');

        DB
            ::table('question_answers')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => null
            ]);

        $answerRepository = new AnswerRepository();
        $answerRepository->createProcess($id);

        return $this->resNoContent();
    }
}