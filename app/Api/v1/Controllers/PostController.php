<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Counter\PostViewCounter;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Comment\PostCommentLikeService;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Post;
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\PostRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("帖子相关接口")
 */
class PostController extends Controller
{
    /**
     * 新建帖子
     *
     * @Post("/post/create")
     *
     * @Transaction({
     *      @Request({"title": "标题，不超过40个字", "bangumiId": "番剧id", "content": "帖子内容，不超过1000个字", "desc": "帖子描述，不超过120个字", "images": "帖子图片列表"}, headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "帖子id"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误", "data": "错误详情"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""})
     * })
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:40',
            'bangumiId' => 'required|integer',
            'desc' => 'required|max:120',
            'content' => 'required|max:1200',
            'images' => 'array'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $bangumiId = $request->get('bangumiId');
        $userId = $this->getAuthUserId();
        $postRepository = new PostRepository();

        $bangumiFollowService = new BangumiFollowService();
        if (!$bangumiFollowService->check($userId, $bangumiId))
        {
            $bangumiFollowService->do($userId, $bangumiId);
            // return $this->resErrRole('关注番剧后才能发帖');
        }

        $now = Carbon::now();

        $id = $postRepository->create([
            'title' => Purifier::clean($request->get('title')),
            'content' => Purifier::clean($request->get('content')),
            'desc' => Purifier::clean($request->get('desc')),
            'bangumi_id' => $bangumiId,
            'user_id' => $userId,
            'target_user_id' => 0,
            'floor_count' => 1,
            'created_at' => $now,
            'updated_at' => $now
        ], $request->get('images'));

        $cacheKey = $postRepository->bangumiListCacheKey($bangumiId);
        if (Redis::EXISTS($cacheKey))
        {
            Redis::ZADD($cacheKey, $now->timestamp, $id);
        }
        Redis::LPUSHX('user_'.$userId.'_minePostIds', $id);

        $job = (new \App\Jobs\Trial\Post\Create($id));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('post/' . $id));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('bangumi/' . $bangumiId, 'update'));
        dispatch($job);

        return $this->resCreated($id);
    }

    /**
     * 帖子信息
     *
     * @Post("/post/${postId}/show")
     *
     * @Transaction({
     *      @Request({"take": "获取数量", "only": "是否只看楼主"}, headers={"Authorization": "Bearer JWT-Token"}, identifier="A"),
     *      @Response(200, body={"code": 0, {"post": "主题帖信息", "list": "帖子列表", "bangumi": "帖子番剧信息", "user": "楼主信息", "total": "帖子总数"}}),
     *      @Request({"seenIds": "看过的postIds，用','隔开的字符串", "take": "获取数量", "only": "是否只看楼主"}, headers={"Authorization": "Bearer JWT-Token"}, identifier="B"),
     *      @Response(200, body={"code": 0, "data": {"list": "帖子列表", "total": "帖子总数"}}),
     *      @Response(400, body={"code": 40004, "message": "不是主题帖", "data": ""}),
     *      @Response(404, body={"code": 40401, "message": "不存在的帖子", "data": ""})
     * })
     */
    public function show(Request $request, $id)
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($id);
        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $userId = $this->getAuthUserId();
        $page = intval($request->get('page')) ?: 0;

        if (!$page)
        {
            $bangumiRepository = new BangumiRepository();
            $bangumi = $bangumiRepository->panel($post['bangumi_id'], $userId);
            if (is_null($bangumi))
            {
                return $this->resErrNotFound('不存在的番剧');
            }
        }

        $commentService = new PostCommentService();

        $only = intval($request->get('only')) ?: 0;
        $take = intval($request->get('take')) ?: 10;
        $replyId = $request->get('replyId') ? intval($request->get('replyId')) : 0;

        $ids = $only
            ? $commentService->onlySeeMasterIds($post['id'], $post['user_id'], $page, $take)
            : $commentService->getMainCommentIds($post['id'], $page, $take);

        if ($replyId && !$page && !$only)
        {
            if (!in_array($replyId, $ids))
            {
                $ids[] = $replyId;
            }
        }

        $list = $commentService->mainCommentList($ids);

        $postCommentLikeService = new PostCommentLikeService();
        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $postCommentLikeService->check($userId, $item['id'], $item['from_user_id']);
        }

        if ($page)
        {
            return $this->resOK([
                'list' => $list
            ]);
        }

        $postCommentService = new PostCommentService();
        $post['commented'] = $postCommentService->check($userId, $id);

        $viewCounter = new PostViewCounter();
        $post['view_count'] = $viewCounter->add($id);

        $postLikeService = new PostLikeService();
        $post['liked'] = $postLikeService->check($userId, $id, $post['user_id']);
        $post['like_count'] = $postLikeService->total($id);
        $post['like_users'] = $postLikeService->users($id);

        $postMarkService = new PostMarkService();
        $post['marked'] = $postMarkService->check($userId, $id, $post['user_id']);
        $post['mark_count'] = $postMarkService->total($id);

        $userRepository = new UserRepository();
        $postTransformer = new PostTransformer();
        $bangumiTransformer = new BangumiTransformer();
        $userTransformer = new UserTransformer();
        $post['preview_images'] = $postRepository->previewImages($id, $post['user_id'], (boolean)$only);

        return $this->resOK([
            'post' => $postTransformer->show($post),
            'list' => $list,
            'bangumi' => $bangumiTransformer->post($bangumi),
            'user' => $userTransformer->item($userRepository->item($post['user_id']))
        ]);
    }

    /**
     * 回复主题帖
     *
     * @Post("/post/${postId}/reply")
     *
     * @Transaction({
     *      @Request({"content": "帖子内容，不超过1000个字", "images": "帖子图片列表"}, headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "帖子对象"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误", "data": "错误详情"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""}),
     *      @Response(404, body={"code": 40401, "message": "不存在的帖子", "data": ""})
     * })
     */
    public function reply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|max:1200',
            'images' => 'array'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $repository = new PostRepository();
        $post = $repository->item($id);
        if(is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $now = Carbon::now();
        $userId = $this->getAuthUserId();
        $images = $request->get('images');

        $saveContent = [];
        foreach ($images as $image)
        {
            $saveContent[] = [
                'type' => 'img',
                'data' => $image
            ];
        }
        $saveContent[] = [
            'type' => 'txt',
            'data' => $request->get('content')
        ];

        $postCommentService = new PostCommentService();
        $newComment = $postCommentService->reply([
            'content' => $saveContent,
            'user_id' => $userId,
            'modal_id' => $id
        ], $userId === intval($post['user_id']));
        if (!$newComment)
        {
            return $this->resErrServiceUnavailable();
        }
        $newId = $newComment['id'];

        $repository->savePostImage($id, $newId, $images);

        Post::where('id', $id)->increment('comment_count');
        Post::where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s',time())
        ]);
        // 更新帖子的缓存
        if (Redis::EXISTS('post_'.$id))
        {
            Redis::HINCRBYFLOAT('post_'.$id, 'comment_count', 1);
            Redis::HSET('post_'.$id, 'updated_at', $now->toDateTimeString());
        }
        // 更新番剧帖子列表的缓存
        $cacheKey = $repository->bangumiListCacheKey($post['bangumi_id']);
        if (Redis::EXISTS($cacheKey))
        {
            Redis::ZADD($cacheKey, $now->timestamp, $id);
        }
        Redis::pipeline(function ($pipe) use ($id, $newId, $userId)
        {
            // 更新用户回复帖子列表的缓存
            $pipe->LPUSHX('user_'.$userId.'_replyPostIds', $newId);
            // 更新帖子楼层的
            $pipe->RPUSHX('post_'.$id.'_ids', $newId);
        });

        if (intval($post['user_id']) !== $userId)
        {
            $job = (new \App\Jobs\Notification\Post\Reply($newId));
            dispatch($job);
        }

        $job = (new \App\Jobs\Push\Baidu('post/' . $id, 'update'));
        dispatch($job);

        return $this->resCreated($newComment);
    }

    /**
     * 获取给帖子点赞的用户列表
     *
     * @Post("/post/${postId}/likeUsers")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的userIds, 用','分割的字符串", "take": "获取的数量"}),
     *      @Response(200, body={"code": 0, "data": "用户列表"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的帖子", "data": ""})
     * })
     */
    public function likeUsers(Request $request, $id)
    {
        $page = $request->get('page') ?: 0;

        $postLikeService = new PostLikeService();
        $users = $postLikeService->users($id, $page);

        return $this->resOK($users);
    }

    /**
     * 给帖子点赞或取消点赞
     *
     * @Post("/post/${postId}/toggleLike")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "是否已赞"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""}),
     *      @Response(403, body={"code": 40301, "message": "不能给自己点赞/金币不足/请求错误", "data": ""}),
     *      @Response(404, body={"code": 40401, "message": "内容已删除", "data": ""})
     * })
     */
    public function toggleLike($postId)
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $userId = $this->getAuthUserId();
        if ($userId === intval($post['user_id']))
        {
            return $this->resErrRole('不能给自己点赞');
        }

        $postLikeService = new PostLikeService();
        $liked = $postLikeService->check($userId, $postId);

        if ($liked)
        {
            $postLikeService->undo($liked, $userId, $postId);

            return $this->resCreated(false);
        }

        $userRepository = new UserRepository();
        $result = $userRepository->toggleCoin($liked, $userId, $post['user_id'], 1, $post['id']);

        if (!$result)
        {
            return $this->resErrRole($liked ? '未打赏过' : '金币不足');
        }

        $likeId = $postLikeService->do($userId, $postId);

        $job = (new \App\Jobs\Notification\Post\Like($likeId));
        dispatch($job);

        $job = (new \App\Jobs\Search\Post\Update($postId, $liked ? -10 : 10));
        dispatch($job);

        return $this->resCreated(true);
    }

    /**
     * 收藏主题帖或取消收藏
     *
     * @Post("/post/${postId}/toggleMark")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "是否已收藏"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""}),
     *      @Response(403, body={"code": 40301, "message": "不能收藏自己的帖子/不是主题帖", "data": ""}),
     *      @Response(404, body={"code": 40401, "message": "不存在的帖子", "data": ""})
     * })
     */
    public function toggleMark($postId)
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $userId = $this->getAuthUserId();
        if ($userId === intval($post['user_id']))
        {
            return $this->resErrRole('不能收藏自己的帖子');
        }

        $postMarkService = new PostMarkService();
        $marked = $postMarkService->toggle($userId, $postId);

        if (!$marked)
        {
            return $this->resCreated(false);
        }

        $job = (new \App\Jobs\Search\Post\Update($postId, $marked ? -10 : 10));
        dispatch($job);

        return $this->resCreated(true);
    }

    /**
     * 删除帖子
     *
     * @Post("/post/${postId}/deletePost")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""}),
     *      @Response(403, body={"code": 40301, "message": "权限不足", "data": ""}),
     *      @Response(404, body={"code": 40401, "message": "不存在的帖子", "data": ""})
     * })
     */
    public function deletePost($postId)
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);
        $userId = $this->getAuthUserId();

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        if (intval($post['user_id']) !== $userId)
        {
            return $this->resErrRole('权限不足');
        }

        DB::table('posts')
            ->where('id', $postId)
            ->update([
                'state' => 1,
                'deleted_at' => Carbon::now()
            ]);
        /*
         * 删除主题帖
         * 删除 bangumi-cache-ids-list 中的这个帖子 id
         * 删除用户帖子列表的id
         * 删除最新和热门帖子下该帖子的缓存
         * 删掉主题帖的缓存
         */
        $bangumiId = $post['bangumi_id'];
        Redis::pipeline(function ($pipe) use ($bangumiId, $postId, $userId)
        {
            $pipe->LREM('user_'.$userId.'_minePostIds', 1, $postId);
            $pipe->ZREM($this->bangumiListCacheKey($bangumiId), $postId);
            $pipe->ZREM('post_new_ids', $postId);
            $pipe->ZREM('post_hot_ids', $postId);
            $pipe->DEL('post_'.$postId);
        });

        $job = (new \App\Jobs\Search\Post\Delete($postId));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('post/' . $postId, 'del'));
        dispatch($job);

        return $this->resNoContent();
    }

    public function deleteComment($commentId)
    {
        $commentService = new PostCommentService();
        $comment = $commentService->getMainCommentItem($commentId);

        if (is_null($comment))
        {
            return $this->resErrNotFound('该评论已被删除');
        }

        $postRepository = new PostRepository();
        $post = $postRepository->item($comment['modal_id']);

        if (is_null($post))
        {
            return $this->resErrNotFound('帖子已经被删除');
        }

        $userId = $this->getAuthUserId();
        $postUserId = intval($post['user_id']);
        $commentCreatorId = $comment['from_user_id'];

        if ($userId !== $commentCreatorId && $userId !== $postUserId)
        {
            return $this->resErrRole('继续操作前请先登录');
        }

        $commentService->deletePostComment(
            $commentId,
            $comment['from_user_id'],
            $comment['modal_id'],
            $postUserId === $commentCreatorId
        );

        return $this->resNoContent();
    }

    /**
     * 最新帖子列表
     *
     * @Post("/trending/post/new")
     *
     * @Transaction({
     *      @Request({"take": "获取数量", "seenIds": "看过的postIds, 用','号分割的字符串"}, headers={"Authorization": "Bearer JWT-Token"}, identifier="A"),
     *      @Response(200, body={"code": 0, {"data": "帖子列表"}}),
     * })
     */
    public function postNew(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $repository = new PostRepository();
        $ids = $repository->getNewIds();

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $userId = $this->getAuthUserId();
        $list = $repository->list(array_slice(array_diff($ids, $seen), 0, $take));

        $postCommentService = new PostCommentService();
        $postLikeService = new PostLikeService();
        $postMarkService = new PostMarkService();
        $postViewCounter = new PostViewCounter();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();

        foreach ($list as $i => $item)
        {
            $id = $item['id'];
            $authorId = $item['user_id'];
            $list[$i]['liked'] = $postLikeService->check($userId, $id, $authorId);
            $list[$i]['marked'] = $postMarkService->check($userId, $id, $authorId);
            $list[$i]['commented'] = $postCommentService->check($userId, $id);
            $list[$i]['view_count'] = $postViewCounter->get($id);
            $list[$i]['user'] = $userRepository->item($authorId);
            $list[$i]['bangumi'] = $bangumiRepository->item($item['bangumi_id']);
        }


        $transformer = new PostTransformer();

        return $this->resOK($transformer->trending($list));
    }

    /**
     * 热门帖子列表
     *
     * @Post("/trending/post/hot")
     *
     * @Transaction({
     *      @Request({"take": "获取数量", "seenIds": "看过的postIds, 用','号分割的字符串"}, headers={"Authorization": "Bearer JWT-Token"}, identifier="A"),
     *      @Response(200, body={"code": 0, {"data": "帖子列表"}}),
     * })
     */
    public function postHot(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $repository = new PostRepository();
        $ids = $repository->getHotIds();

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $userId = $this->getAuthUserId();
        $list = $repository->list(array_slice(array_diff($ids, $seen), 0, $take));

        $postCommentService = new PostCommentService();
        $postLikeService = new PostLikeService();
        $postMarkService = new PostMarkService();
        $postViewCounter = new PostViewCounter();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();

        foreach ($list as $i => $item)
        {
            $id = $item['id'];
            $authorId = $item['user_id'];
            $list[$i]['liked'] = $postLikeService->check($userId, $id, $authorId);
            $list[$i]['marked'] = $postMarkService->check($userId, $id, $authorId);
            $list[$i]['commented'] = $postCommentService->check($userId, $id);
            $list[$i]['view_count'] = $postViewCounter->get($id);
            $list[$i]['user'] = $userRepository->item($authorId);
            $list[$i]['bangumi'] = $bangumiRepository->item($item['bangumi_id']);
        }

        $transformer = new PostTransformer();

        return $this->resOK($transformer->trending($list));
    }
}
