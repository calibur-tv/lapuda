<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 上午9:45
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\Comment\AnswerCommentService;
use App\Api\V1\Services\Comment\CartoonRoleCommentService;
use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Comment\QuestionCommentService;
use App\Api\V1\Services\Comment\ScoreCommentService;
use App\Api\V1\Services\Comment\VideoCommentService;
use App\Api\V1\Services\Counter\Stats\TotalCommentCount;
use App\Api\V1\Services\UserLevel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @Resource("评论相关接口")
 */
class CommentController extends Controller
{
    public function __construct()
    {
        $this->types = [
            'post',
            'image',
            'score',
            'video',
            'question',
            'answer',
            'role'
        ];
    }

    /**
     * 新建主评论
     *
     * @Post("/comment/main/create")
     *
     * @Parameters({
     *      @Parameter("content", description="内容，`1000字以内`", type="string", required=true),
     *      @Parameter("images", description="图片对象数组", type="array", required=true),
     *      @Parameter("type", description="某个 type", type="string", required=true),
     *      @Parameter("id", description="评论主题的 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "主评论对象"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"})
     * })
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|max:1200',
            'images' => 'array',
            'id' => 'required|integer|min:1',
            'type' => [
                'required',
                Rule::in($this->types),
            ],
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

        $type = $request->get('type');
        $id = $request->get('id');

        $commentService = $this->getCommentServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrBad('错误的类型');
        }

        $repository = $this->getRepositoryByType($type);
        if (is_null($repository))
        {
            return $this->resErrBad('错误的类型');
        }

        $parent = $repository->item($id);
        if (is_null($parent))
        {
            return $this->resErrNotFound();
        }

        $saveContent = [];
        $userId = $this->getAuthUserId();
        $masterId = isset($parent['user_id']) ? intval($parent['user_id']) : 0;

        foreach ($images as $image)
        {
            $saveContent[] = [
                'type' => 'img',
                'data' => [
                    // 历史遗留问题，这里叫 key
                    'key' => $repository->convertImagePath($image['url']),
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'type' => $image['type'],
                    'size' => $image['size']
                ]
            ];
        }

        $content = $request->get('content');
        $saveContent[] = [
            'type' => 'txt',
            'data' => $repository->formatRichContent($content)
        ];

        $newComment = $commentService->create([
            'content' => $saveContent,
            'user_id' => $userId,
            'modal_id' => $id,
            'to_user_id' => $masterId
        ]);

        if (!$newComment)
        {
            return $this->resErrServiceUnavailable();
        }

        $repository->applyComment($id, $newComment['id'], $images);

        $job = (new \App\Jobs\Notification\Create(
            $type . '-comment',
            $masterId,
            $userId,
            $id,
            $newComment['id']
        ));
        dispatch($job);

        if (!in_array($type, ['role']))
        {
            $job = (new \App\Jobs\Trending\Active(
                $id,
                $type,
                $parent['bangumi_id']
            ));
            dispatch($job);
        }

        $newComment['liked'] = false;
        $newComment['like_count'] = 0;

        $totalCommentCount = new TotalCommentCount();
        $totalCommentCount->add();

        $exp = 0;
        if (!isset($parent['user_id']) || $parent['user_id'] != $userId)
        {
            $userLevel = new UserLevel();
            $exp = $userLevel->change($userId, 2, $content);
        }

        return $this->resCreated([
            'data' => $newComment,
            'exp' => $exp,
            'message' => $exp ? "评论成功，经验+{$exp}" : "评论成功"
        ]);
    }

    /**
     * 获取主评论列表
     *
     * @Get("/comment/main/list")
     *
     * @Parameters({
     *      @Parameter("type", description="某个 type", type="string", required=true),
     *      @Parameter("id", description="如果是帖子，则是帖子id", type="integer", required=true),
     *      @Parameter("fetchId", description="你通过这个接口获取的评论列表里最后的那个id", type="integer", default="0", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": {"list": "主评论列表", "total": "总数", "noMore": "没有更多了"}}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"})
     * })
     */
    public function mainList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fetchId' => 'required',
            'id' => 'required|integer|min:1',
            'type' => [
                'required',
                Rule::in($this->types),
            ],
        ]);

        if ($validator->fails())
        {
            return $this->resErrBad();
        }

        $type = $request->get('type');
        $id = $request->get('id');

        $commentService = $this->getCommentServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrBad('错误的类型');
        }

        $repository = $this->getRepositoryByType($type);
        if (is_null($repository))
        {
            return $this->resErrBad('错误的类型');
        }

        $parent = $repository->item($id);
        if (is_null($parent))
        {
            return $this->resErrNotFound();
        }

        $take = 10;
        $fetchId = intval($request->get('fetchId')) ?: 0;
        $onlySeeMaster = intval($request->get('onlySeeMaster')) ?: 0;
        $seeReplyId = intval($request->get('seeReplyId')) ?: 0;

        $ids = $onlySeeMaster
            ? $commentService->getAuthorMainCommentIds($id, $parent['user_id'])
            : $commentService->getMainCommentIds($id);

        $idsObject = $this->filterIdsByMaxId($ids, $fetchId, $take);
        $userId = $this->getAuthUserId();

        // 获取第一页数据，并且指明要看某一条数据
        if (!$fetchId && $seeReplyId)
        {
            $replyIndex = array_search($seeReplyId, $ids);
            if ($replyIndex && $replyIndex >= $take)
            {
                array_push($idsObject['ids'], $seeReplyId);
            }
        }

        $list = $commentService->mainCommentList($idsObject['ids']);
        $list = $commentService->batchCheckLiked($list, $userId, 'liked');
        $list = $commentService->batchGetLikeCount($list, 'like_count');

        return $this->resOK([
            'list' => $list,
            'total' => $idsObject['total'],
            'noMore' => $idsObject['noMore']
        ]);
    }

    /**
     * 子评论列表
     *
     * > 一个通用的接口，通过 `type` 和 `commentId` 来获取子评论列表.
     *
     * > `commentId`是父评论的 id：
     * 1. `父评论` 一部视频下的评论列表，列表中的每一个就是一个父评论
     * 2. `子评论` 每个父评论都有回复列表，这个回复列表中的每一个就是子评论
     *
     * @Get("/comment/sub/list")
     *
     * @Parameters({
     *      @Parameter("type", description="某种 type", type="string", required=true),
     *      @Parameter("id", description="父评论 id", type="integer", required=true),
     *      @Parameter("maxId", description="该父评论下看过的最大的子评论 id", type="integer", default=0, required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"list": "评论列表", "total": "评论总数", "noMore": "没有更多了"}}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的父评论"})
     * })
     */
    public function subList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'maxId' => 'required',
            'id' => 'required|integer|min:1',
            'type' => [
                'required',
                Rule::in($this->types),
            ],
        ]);

        if ($validator->fails())
        {
            return $this->resErrBad();
        }

        $type = $request->get('type');
        $id = $request->get('id');

        $commentService = $this->getCommentServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrBad('错误的类型');
        }

        $comment = $commentService->getMainCommentItem($id);
        if (is_null($comment))
        {
            return $this->resErrNotFound('不存在的评论');
        }

        $take = 10;
        $maxId = intval($request->get('maxId')) ?: 0;

        $ids = $commentService->getSubCommentIds($id);
        $idsObject = $this->filterIdsByMaxId($ids, $maxId, $take);

        $comments = $commentService->subCommentList($idsObject['ids']);

        return $this->resOK([
            'list' => $comments,
            'total' => $idsObject['total'],
            'noMore' => $idsObject['noMore']
        ]);
    }

    /**
     * 回复评论
     *
     * @Post("/comment/main/reply")
     *
     * @Parameters({
     *      @Parameter("type", description="某种 type", type="string", required=true),
     *      @Parameter("id", description="父评论 id", type="integer", required=true),
     *      @Parameter("targetUserId", description="父评论的用户 id", type="integer", required=true),
     *      @Parameter("content", description="评论内容，`纯文本，100字以内`", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "子评论对象"}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"}),
     *      @Response(404, body={"code": 40401, "message": "内容已删除"})
     * })
     */
    public function reply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|max:100',
            'targetUserId' => 'required|Integer',
            'id' => 'required|integer|min:1',
            'type' => [
                'required',
                Rule::in($this->types),
            ],
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $type = $request->get('type');
        $id = $request->get('id');

        $commentService = $this->getCommentServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrBad('错误的类型');
        }

        $comment = $commentService->getMainCommentItem($id);
        if (is_null($comment))
        {
            return $this->resErrNotFound('内容已删除');
        }

        $content = $request->get('content');
        $targetUserId = intval($request->get('targetUserId'));
        $userId = $this->getAuthUserId();

        $newComment = $commentService->create([
            'content' => $content,
            'to_user_id' => $userId === $targetUserId ? 0 : $targetUserId,
            'user_id' => $userId,
            'parent_id' => $id
        ]);

        if (is_null($newComment))
        {
            return $this->resErrServiceUnavailable();
        }

        if (!in_array($type, ['question', 'answer']))
        {
            $job = (new \App\Jobs\Notification\Create(
                $type . '-reply',
                $targetUserId,
                $userId,
                $comment['modal_id'],
                $id,
                $newComment['id']
            ));
            dispatch($job);
        }

        $totalCommentCount = new TotalCommentCount();
        $totalCommentCount->add();

        $exp = 0;
        if ($userId !== $targetUserId)
        {
            $userLevel = new UserLevel();
            $exp = $userLevel->change($userId, 1, $content);
        }

        return $this->resCreated([
            'data' => $newComment,
            'exp' => $exp,
            'message' => $exp ? "回复成功，经验+{$exp}" : "回复成功"
        ]);
    }

    /**
     * 删除主评论
     *
     * @Post("/comment/main/delete")
     *
     * @Parameters({
     *      @Parameter("type", description="某种 type", type="string", required=true),
     *      @Parameter("id", description="父评论 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "message": "参数错误"}),
     *      @Response(404, body={"code": 40401, "message": "该评论已被删除"}),
     *      @Response(403, body={"code": 40301, "message": "继续操作前请先登录"})
     * })
     */
    public function deleteMainComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
            'type' => [
                'required',
                Rule::in($this->types),
            ],
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $type = $request->get('type');
        $id = $request->get('id');

        $commentService = $this->getCommentServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrBad('错误的类型');
        }

        $comment = $commentService->getMainCommentItem($id);
        if (is_null($comment))
        {
            return $this->resErrNotFound('该评论已被删除');
        }

        $userId = $this->getAuthUserId();
        $isMaster = false;
        // 不是评论的作者
        if ($userId !== $comment['from_user_id'])
        {
            $repository = $this->getRepositoryByType($type);
            $parent = $repository->item($comment['modal_id']);
            // 不是主题的作者
            if ($parent['user_id'] != $userId)
            {
                return $this->resErrRole();
            }
            else
            {
                $isMaster = true;
            }
        }

        $commentService->deleteMainComment($id, $comment['modal_id'], $comment['from_user_id'], $isMaster);

        $job = (new \App\Jobs\Notification\Delete(
            $type . '-comment',
            $comment['to_user_id'],
            $comment['from_user_id'],
            $comment['modal_id'],
            $comment['id']
        ));
        dispatch($job);

        $exp = 0;
        if (!$isMaster)
        {
            $userLevel = new UserLevel();
            $exp = $userLevel->change($comment['from_user_id'], -2, $comment['content']);
        }

        return $this->resOK([
            'exp' => $exp,
            'message' => $exp ? "删除成功，经验{$exp}" : "删除成功"
        ]);
    }

    /**
     * <喜欢/取消喜欢>主评论
     *
     * @Post("/comment/main/toggleLike")
     *
     * @Parameters({
     *      @Parameter("type", description="某种 type", type="string", required=true),
     *      @Parameter("id", description="父评论 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "是否已喜欢"}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"})
     * })
     */
    public function toggleLikeMainComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
            'type' => [
                'required',
                Rule::in($this->types),
            ],
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $type = $request->get('type');
        $id = $request->get('id');

        $commentService = $this->getCommentServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrBad('错误的类型');
        }

        $comment = $commentService->getMainCommentItem($id);
        if (is_null($comment))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        $result = $commentService->toggleLike($userId, $id);

        if ($result)
        {
            $job = (new \App\Jobs\Notification\Create(
                $type . '-comment-like',
                $comment['from_user_id'],
                $userId,
                $comment['modal_id'],
                $comment['id']
            ));
            dispatch($job);
        }
        else
        {
            $job = (new \App\Jobs\Notification\Delete(
                $type . '-comment-like',
                $comment['from_user_id'],
                $userId,
                $comment['modal_id'],
                $comment['id']
            ));
            dispatch($job);
        }

        return $this->resCreated((boolean)$result);
    }

    /**
     * <喜欢/取消喜欢>子评论
     *
     * @Post("/comment/sub/toggleLike")
     *
     * @Parameters({
     *      @Parameter("type", description="某种 type", type="string", required=true),
     *      @Parameter("id", description="父评论 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "是否已喜欢"}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"})
     * })
     */
    public function toggleLikeSubComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
            'type' => [
                'required',
                Rule::in($this->types),
            ],
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $type = $request->get('type');
        $id = $request->get('id');

        $commentService = $this->getCommentServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrBad('错误的类型');
        }
        $comment = $commentService->getSubCommentItem($id);
        if (is_null($comment))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        $result = $commentService->toggleLike($userId, $id);
        if ($result)
        {
            $job = (new \App\Jobs\Notification\Create(
                $type . '-reply-like',
                $comment['from_user_id'],
                $userId,
                $comment['modal_id'],
                $comment['parent_id'],
                $comment['id']
            ));
            dispatch($job);
        }
        else
        {
            $job = (new \App\Jobs\Notification\Delete(
                $type . '-reply-like',
                $comment['from_user_id'],
                $userId,
                $comment['modal_id'],
                $comment['parent_id'],
                $comment['id']
            ));
            dispatch($job);
        }

        return $this->resCreated((boolean)$result);
    }

    /**
     * 删除子评论
     *
     * @Post("/comment/sub/delete")
     *
     * @Parameters({
     *      @Parameter("type", description="某种 type", type="string", required=true),
     *      @Parameter("id", description="子评论 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "message": "参数错误"}),
     *      @Response(404, body={"code": 40401, "message": "该评论已被删除"}),
     *      @Response(403, body={"code": 40301, "message": "继续操作前请先登录"})
     * })
     */
    public function deleteSubComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
            'type' => [
                'required',
                Rule::in($this->types),
            ],
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $type = $request->get('type');
        $id = $request->get('id');

        $commentService = $this->getCommentServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrBad('错误的类型');
        }

        $comment = $commentService->getSubCommentItem($id);
        if (is_null($comment))
        {
            return $this->resErrNotFound('该评论已被删除');
        }

        $userId = $this->getAuthUserId();
        // 不是子评论的作者
        if ($userId !== $comment['from_user_id'])
        {
            $parent = $commentService->getMainCommentItem($comment['parent_id']);
            // 不是主评论作者
            if ($parent['from_user_id'] != $userId)
            {
                return $this->resErrRole();
            }
        }

        $commentService->deleteSubComment($id, $comment['parent_id']);

        $job = (new \App\Jobs\Notification\Delete(
            $type . '-reply',
            $comment['to_user_id'],
            $comment['from_user_id'],
            $comment['parent_id'],
            $comment['id']
        ));
        dispatch($job);

        $exp = 0;
        if ($comment['to_user_id'] != 0)
        {
            $userLevel = new UserLevel();
            $exp = $userLevel->change($comment['from_user_id'], -1, $comment['content']);
        }

        return $this->resOK([
            'exp' => $exp,
            'message' => $exp ? "删除成功，经验{$exp}" : '删除成功'
        ]);
    }

    // 后台待审的评论
    public function trials()
    {
        $result = [];
        foreach ($this->types as $modal)
        {
            if ($modal === 'role')
            {
                $modal = 'cartoon_role';
            }
            $list = DB::table($modal . '_comments')
                ->where('state', '<>', 0)
                ->select('id', 'user_id', 'content', 'modal_id', 'parent_id', 'deleted_at')
                ->take(15)
                ->get();

            if (is_null($list))
            {
                continue;
            }

            $list = json_decode(json_encode($list), true);
            foreach ($list as $i => $item)
            {
                $list[$i]['type'] = $modal;
                if ($item['modal_id'] == 0)
                {
                    $repository = $this->getCommentServiceByType($modal);
                    $mainComment = $repository->getMainCommentItem($item['parent_id']);
                    $list[$i]['modal_id'] = $mainComment['modal_id'];
                }
            }

            if (!is_null($list))
            {
                $result = array_merge($result, $list);
            }
        }

        return $this->resOK([
            'comments' => $result,
            'types' => $this->types
        ]);
    }

    // 后台删除评论
    public function ban(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        if ($type === 'role')
        {
            $type = 'cartoon_role';
        }
        $now = Carbon::now();

        $comment = DB
            ::table($type . '_comments')
            ->where('id', $id)
            ->first();

        DB::table($type . '_comments')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => $now
            ]);

        $userLevel = new UserLevel();
        $commentService = $this->getCommentServiceByType($type);
        if ($comment->parent_id == 0)
        {
            // 主评论
            $comment = $commentService->getMainCommentItem($id);
            if (is_null($comment))
            {
                return $this->resNoContent();
            }
            $commentService->deleteMainComment(
                $id,
                $comment['modal_id'],
                $comment['from_user_id'],
                true
            );
            $userLevel->change($comment['from_user_id'], -2, false);
        }
        else
        {
            // 子评论
            $comment = $commentService->getSubCommentItem($id);
            if (is_null($comment))
            {
                return $this->resNoContent();
            }
            $commentService->deleteSubComment($id, $comment['parent_id']);

            $userLevel->change($comment['from_user_id'], -1, false);
        }

        return $this->resNoContent();
    }

    // 后台通过评论
    public function pass(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        if ($type === 'role')
        {
            $type = 'cartoon_role';
        }

        DB
            ::table($type . '_comments')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => null
            ]);

        return $this->resNoContent();
    }

    // 后台确认删除
    public function approve(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');

        if ($type === 'role')
        {
            $type = 'cartoon_role';
        }

        DB
            ::table($type . '_comments')
            ->where('id', $id)
            ->update([
                'state' => 0
            ]);

        return $this->resNoContent();
    }

    // 后台驳回删除
    public function reject(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $parentId = intval($request->get('parent_id'));

        if ($type === 'role')
        {
            $type = 'cartoon_role';
        }

        DB
            ::table($type . '_comments')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => null
            ]);

        $commentService = $this->getCommentServiceByType($type);
        if ($parentId)
        {
            $commentService->changeSubCommentTotal($id, $parentId);
        }
        else
        {
            $commentService->changeMainCommentTotal($id);
        }

        return $this->resNoContent();
    }

    // 后台批量删某用户的评论
    public function batchBan(Request $request)
    {
        $userId = $request->get('user_id');
        $type = $request->get('type');
        if ($type === 'role')
        {
            $type = 'cartoon_role';
        }

        $commentIds = DB
            ::table($type . '_comments')
            ->where('user_id', $userId)
            ->select('id', 'parent_id')
            ->whereNull('deleted_at')
            ->get()
            ->toArray();

        $userLevel = new UserLevel();
        $commentService = $this->getCommentServiceByType($type);
        foreach ($commentIds as $comment)
        {
            $id = $comment->id;
            if ($comment->parent_id == 0)
            {
                // 主评论
                $comment = $commentService->getMainCommentItem($id);
                if (is_null($comment))
                {
                    return $this->resNoContent();
                }
                $commentService->deleteMainComment(
                    $id,
                    $comment['modal_id'],
                    $comment['from_user_id'],
                    true
                );
                $userLevel->change($comment['from_user_id'], -2, false);
            }
            else
            {
                // 子评论
                $comment = $commentService->getSubCommentItem($id);
                if (is_null($comment))
                {
                    return $this->resNoContent();
                }
                $commentService->deleteSubComment($id, $comment['parent_id']);

                $userLevel->change($comment['from_user_id'], -1, false);
            }
        }
        return $this->resNoContent();
    }

    public function batchPass(Request $request)
    {
        $passArr = $request->get('pass_arr');
        $approveArr = $request->get('approve_arr');

        foreach ($passArr as $comment)
        {
            $id = $comment['id'];
            $type = $comment['type'];
            if ($type === 'role')
            {
                $type = 'cartoon_role';
            }

            DB
                ::table($type . '_comments')
                ->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => null
                ]);
        }

        foreach ($approveArr as $comment)
        {
            $id = $comment['id'];
            $type = $comment['type'];
            if ($type === 'role')
            {
                $type = 'cartoon_role';
            }

            DB
                ::table($type . '_comments')
                ->where('id', $id)
                ->update([
                    'state' => 0
                ]);
        }

        return $this->resNoContent();
    }

    protected function getCommentServiceByType($type)
    {
        if ($type === 'post')
        {
            return new PostCommentService();
        }
        else if ($type === 'video')
        {
            return new VideoCommentService();
        }
        else if ($type === 'image')
        {
            return new ImageCommentService();
        }
        else if ($type === 'score')
        {
            return new ScoreCommentService();
        }
        else if ($type === 'question')
        {
            return new QuestionCommentService();
        }
        else if ($type === 'answer')
        {
            return new AnswerCommentService();
        }
        else if ($type === 'role')
        {
            return new CartoonRoleCommentService();
        }
        else if ($type === 'cartoon_role')
        {
            return new CartoonRoleCommentService();
        }
        else
        {
            return null;
        }
    }

    protected function getRepositoryByType($type)
    {
        if ($type === 'post')
        {
            return new PostRepository();
        }
        else if ($type === 'video')
        {
            return new VideoRepository();
        }
        else if ($type === 'image')
        {
            return new ImageRepository();
        }
        else if ($type === 'score')
        {
            return new ScoreRepository();
        }
        else if ($type === 'question')
        {
            return new QuestionRepository();
        }
        else if ($type === 'answer')
        {
            return new AnswerRepository();
        }
        else if ($type === 'role')
        {
            return new CartoonRoleRepository();
        }
        else
        {
            return null;
        }
    }
}
