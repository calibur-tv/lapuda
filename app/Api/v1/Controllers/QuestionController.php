<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 上午6:27
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Services\UserLevel;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("提问相关接口")
 */
class QuestionController extends Controller
{
    /**
     * 获取提问详情
     *
     * @Get("/question/qaq/{id}/show")
     *
     * @Transaction({
     *      @Response(423, body={"code": 42301, "message": "内容正在审核中"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的漫评"}),
     *      @Response(200, body="详情")
     * })
     */
    public function show($id)
    {
        $questionRepository = new QuestionRepository();
        $question = $questionRepository->item($id, true);
        if (is_null($question))
        {
            return $this->resErrNotFound();
        }

        if ($question['deleted_at'])
        {
            if ($question['state'])
            {
                return $this->resErrLocked();
            }

            return $this->resErrNotFound();
        }

        return $this->resOK($questionRepository->show($id, $this->getAuthUserId()));
    }

    /**
     * 创建提问
     *
     * @Post("/question/qaq/cerate")
     *
     * @Parameters({
     *      @Parameter("tags", description="番剧的id数字", type="array", required=true),
     *      @Parameter("title", description="标题", type="string", required=true),
     *      @Parameter("images", description="图片列表", type="array"),
     *      @Parameter("intro", description="纯文本简介，120字", type="string", required=true),
     *      @Parameter("content", description="正题，1000字以内", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(400, body={"code": 40001, "message": "错误的请求参数"}),
     *      @Response(201, body={"code": 0, "data": "提问的id"})
     * })
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|Array',
            'title' => 'required|string|max:30',
            'images' => 'Array',
            'content' => 'required|string|max:1000'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $images = $request->get('images') ?: [];
        foreach ($images as $i => $image)
        {
            $validator = Validator::make($image, [
                'url' => 'required|string',
                'width' => 'required|integer',
                'height' => 'required|integer',
                'size' => 'required|integer',
                'type' => 'required|string',
            ]);

            if ($validator->fails())
            {
                return $this->resErrParams($validator);
            }
        }

        $userId = $this->getAuthUserId();
        $content = Purifier::clean($request->get('content'));
        $questionRepository = new QuestionRepository();
        $newId = $questionRepository->create([
            'tags' => $request->get('tags'),
            'title' => $request->get('title'),
            'text' => $request->get('content'),
            'images' => $request->get('images'),
            'user_id' => $userId
        ]);

        $userLevel = new UserLevel();
        $exp = $userLevel->change($userId, 3, $content);

        return $this->resCreated([
            'data' => $newId,
            'exp' => $exp,
            'message' => $exp ? "提问成功，经验+{$exp}" : "提问成功"
        ]);
    }

    // TODO：更新问题
    public function update()
    {

    }

    // TODO：删除问题
    public function delete()
    {

    }

    // 后台审核列表
    public function trials()
    {
        $ids = Question
            ::withTrashed()
            ->where('state', '<>', 0)
            ->pluck('id')
            ->toArray();

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $questionRepository = new QuestionRepository();
        $list = $questionRepository->list($ids, true);

        return $this->resOK($list);
    }

    // 后台删除问题
    public function ban(Request $request)
    {
        $id = $request->get('id');

        $questionRepository = new QuestionRepository();
        $questionRepository->deleteProcess($id);

        return $this->resNoContent();
    }

    // 后台通过问题
    public function pass(Request $request)
    {
        $id = $request->get('id');

        $questionRepository = new QuestionRepository();
        $questionRepository->recoverProcess($id);

        return $this->resNoContent();
    }
}