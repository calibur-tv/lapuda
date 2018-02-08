<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Post;
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Models\PostLike;
use App\Models\PostMark;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            'content' => 'required|max:1000',
            'images' => 'array'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $now = Carbon::now();
        $bangumiId = $request->get('bangumiId');
        $userId = $this->getAuthUserId();
        $repository = new PostRepository();

        $id = $repository->create([
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

        $cacheKey = $repository->bangumiListCacheKey($bangumiId);
        Redis::pipeline(function ($pipe) use ($id, $cacheKey, $now, $userId)
        {
            if ($pipe->EXISTS($cacheKey))
            {
                $pipe->ZADD($cacheKey, $now->timestamp, $id);
            }
            $pipe->LPUSHX('user_'.$userId.'_minePostIds', $id);
        });

        $job = (new \App\Jobs\Trial\Post\Create($id))->onQueue('post-create');
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

        if (intval($post['parent_id']) !== 0)
        {
            return $this->resErrBad('不是主题帖');
        }

        $userId = $this->getAuthUserId();
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;
        $take = empty($seen) ? $take - 1 : $take;
        $only = intval($request->get('only')) ?: 0;
        $ids = $postRepository->getPostIds($id, $only ? $post['user_id'] : false);

        $list = $postRepository->list(array_slice(array_diff($ids, $seen), 0, $take));

        Post::where('id', $post['id'])->increment('view_count');
        if (Redis::EXISTS('post_'.$id))
        {
            Redis::HINCRBYFLOAT('post_'.$id, 'view_count', 1);
        }

        $postTransformer = new PostTransformer();
        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $userId ? $postRepository->checkPostLiked($item['id'], $userId) : false;
        }

        $list = $postTransformer->reply($list);

        if (!empty($seen))
        {
            return $this->resOK([
                'list' => $list,
                'total' => count($ids) + 1
            ]);
        }

        $post['liked'] = $userId ? $postRepository->checkPostLiked($id, $userId) : false;
        $post['marked'] = $userId ? $postRepository->checkPostMarked($id, $userId) : false;

        $bangumiRepository = new BangumiRepository();
        $userRepository = new UserRepository();
        $bangumiTransformer = new BangumiTransformer();
        $userTransformer = new UserTransformer();
        $post['previewImages'] = $postRepository->previewImages($id, $only ? $post['user_id'] : false);

        return $this->resOK([
            'post' => $postTransformer->show($post),
            'list' => $list,
            'bangumi' => $bangumiTransformer->item($bangumiRepository->item($post['bangumi_id'])),
            'user' => $userTransformer->item($userRepository->item($post['user_id'])),
            'total' => count($ids) + 1
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
            'content' => 'required|max:1000',
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
        $count = Post::where('parent_id', $id)->count();

        $images = $request->get('images');
        $newId = $repository->create([
            'content' => Purifier::clean($request->get('content')),
            'parent_id' => $id,
            'user_id' => $userId,
            'target_user_id' => $post['user_id'],
            'floor_count' => $count + 2,
            'created_at' => $now,
            'updated_at' => $now
        ], $images);

        Post::where('id', $id)->increment('comment_count');
        $cacheKey = $repository->bangumiListCacheKey($post['bangumi_id']);
        Redis::pipeline(function ($pipe) use ($id, $cacheKey, $now, $newId, $images, $userId)
        {
            // 更新帖子的缓存
            if ($pipe->EXISTS('post_'.$id))
            {
                $pipe->HINCRBYFLOAT('post_'.$id, 'comment_count', 1);
                $pipe->HSET('post_'.$id, 'updated_at', $now->toDateTimeString());
            }
            // 更新帖子楼层的
            $pipe->RPUSHX('post_'.$id.'_ids', $newId);
            // 更新帖子图片预览的缓存
            if ($pipe->EXISTS('post_'.$id.'_previewImages') && !empty($images))
            {
                foreach ($images as $i => $val)
                {
                    $images[$i] = config('website.image') . $val['key'];
                }
                $pipe->RPUSH('post_'.$id.'_previewImages', $images);
            }
            // 更新用户回复帖子列表的缓存
            $pipe->LPUSHX('user_'.$userId.'_replyPostIds', $newId);
            // 更新番剧帖子列表的缓存
            if ($pipe->EXISTS($cacheKey))
            {
                $pipe->ZADD($cacheKey, $now->timestamp, $id);
            }
        });

        $reply = $repository->item($newId);
        $reply['liked'] = false;
        $transformer = new PostTransformer();

        if (intval($post['user_id']) !== $userId)
        {
            $job = (new \App\Jobs\Notification\Post\Reply($newId))->onQueue('notification-post-reply');
            dispatch($job);
        }
        $job = (new \App\Jobs\Trial\Post\Reply($id))->onQueue('post-reply');
        dispatch($job);

        return $this->resCreated($transformer->reply([$reply])[0]);
    }

    /**
     * 评论回复贴
     *
     * @Post("/post/${postId}/comment")
     *
     * @Transaction({
     *      @Request({"content": "回复内容，不超过50个字"}, headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "评论对象"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误", "data": "错误详情"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""}),
     *      @Response(404, body={"code": 40401, "message": "内容已删除", "data": ""})
     * })
     */
    public function comment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|max:50'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $repository = new PostRepository();
        $post = $repository->item($id);
        if (is_null($post))
        {
            return $this->resErrNotFound('内容已删除');
        }

        $now = Carbon::now();
        $userId = $this->getAuthUserId();
        $targetUserId = $request->get('targetUserId');

        $newId = $repository->create([
            'content' => Purifier::clean($request->get('content')),
            'parent_id' => $id,
            'user_id' => $userId,
            'target_user_id' => $targetUserId,
            'created_at' => $now,
            'updated_at' => $now
        ], []);

        Post::where('id', $id)->increment('comment_count');
        Redis::pipeline(function ($pipe) use ($id, $newId, $userId)
        {
            if ($pipe->EXISTS('post_'.$id))
            {
                $pipe->HINCRBYFLOAT('post_'.$id, 'comment_count', 1);
            }
            $pipe->RPUSHX('post_'.$id.'_commentIds', $newId);
            $pipe->LPUSHX('user_'.$userId.'_replyPostIds', $newId);
        });

        $postTransformer = new PostTransformer();

        if (intval($targetUserId) !== 0)
        {
            $job = (new \App\Jobs\Notification\Post\Comment($newId))->onQueue('notification-post-comment');
            dispatch($job);
        }
        $job = (new \App\Jobs\Trial\Post\Comment($id))->onQueue('post-comment');
        dispatch($job);

        return $this->resCreated($postTransformer->comments([$repository->comment($id, $newId)])[0]);
    }

    /**
     * 获取评论列表
     *
     * @Post("/post/${postId}/comments")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的commentIds, 用','分割的字符串"}),
     *      @Response(200, body={"code": 0, "data": "评论列表"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的帖子", "data": ""})
     * })
     */
    public function comments(Request $request, $id)
    {
        $repository = new PostRepository();
        $post = $repository->item($id);

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $data = $repository->comments(
            $id,
            $request->get('seenIds')
                ? explode(',', $request->get('seenIds'))
                : []
        );

        $postTransformer = new PostTransformer();

        return $this->resOK($postTransformer->comments($data));
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
        $repository = new PostRepository();
        $post = $repository->item($id);

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $data = $repository->likeUsers($id, $seen, $take);

        if (empty($data))
        {
            return $this->resOK([]);
        }

        $userTransformer = new UserTransformer();

        return $this->resOK($userTransformer->list($data));
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

        $liked = $postRepository->checkPostLiked($postId, $userId);

        // 如果是主题帖，要删除楼主所得的金币，但金币不返还给用户
        $isMainPost = intval($post['parent_id']) === 0;
        if ($isMainPost)
        {
            $userRepository = new UserRepository();
            $result = $userRepository->toggleCoin($liked, $userId, $post['user_id'], 1, $post['id']);

            if (!$result)
            {
                return $this->resErrRole($liked ? '未打赏过' : '金币不足');
            }
        }

        if ($liked)
        {
            PostLike::whereRaw('user_id = ? and post_id = ?', [$userId, $postId])->delete();
            $num = -1;
        }
        else
        {
            $likeId = PostLike::insertGetId([
                'user_id' => $userId,
                'post_id' => $postId
            ]);
            $num = 1;

            if ($isMainPost)
            {
                $job = (new \App\Jobs\Notification\Post\Like($likeId))->onQueue('notification-post-like');
            }
            else
            {
                $job = (new \App\Jobs\Notification\Post\Agree($likeId))->onQueue('notification-post-agree');
            }
            dispatch($job);
        }

        Post::where('id', $post['id'])->increment('like_count', $num);
        if (Redis::EXISTS('post_'.$postId))
        {
            Redis::HINCRBYFLOAT('post_'.$postId, 'like_count', $num);
        }

        return $this->resCreated(!$liked);
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

        if (intval($post['parent_id']) !== 0)
        {
            return $this->resErrBad('不是主题帖');
        }

        $userId = $this->getAuthUserId();
        if ($userId === intval($post['user_id']))
        {
            return $this->resErrRole('不能收藏自己的帖子');
        }

        $marked = $postRepository->checkPostMarked($postId, $userId);
        if ($marked)
        {
            PostMark::whereRaw('user_id = ? and post_id = ?', [$userId, $postId])->delete();
            $num = -1;
        }
        else
        {
            PostMark::create([
                'user_id' => $userId,
                'post_id' => $postId
            ]);
            $num = 1;
        }

        Post::where('id', $post['id'])->increment('mark_count', $num);
        if (Redis::EXISTS('post_'.$postId))
        {
            Redis::HINCRBYFLOAT('post_'.$postId, 'mark_count', $num);
        }

        return $this->resCreated(!$marked);
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

        $delete = false;
        $state = 0;
        if (intval($post['user_id']) === $userId)
        {
            $delete = true;
            $state = 1;
        }
        else if (intval($post['parent_id']) !== 0)
        {
            $post = $postRepository->item($post['parent_id']);
            if (intval($post['user_id']) === $userId)
            {
                $delete = true;
                $state = 2;
            }
        }

        if (!$delete)
        {
            return $this->resErrRole('权限不足');
        }

        $postRepository->deletePost($post, $state);

        return $this->resNoContent();
    }

    /**
     * 删除评论
     *
     * @Post("/post/${postId}/deleteComment")
     *
     * @Transaction({
     *      @Request({"commentId": "评论的id"}, headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""}),
     *      @Response(403, body={"code": 40301, "message": "权限不足", "data": ""}),
     *      @Response(404, body={"code": 40401, "message": "不存在的评论", "data": ""})
     * })
     */
    public function deleteComment(Request $request, $postId)
    {
        $commentId = $request->get('id');
        $postRepository = new PostRepository();
        $comment = $postRepository->comment($postId, $commentId);

        if (is_null($comment))
        {
            return $this->resErrNotFound('不存在的评论');
        }

        $userId = $this->getAuthUserId();
        if (intval($comment['from_user_id']) !== $userId)
        {
            return $this->resErrRole('权限不足');
        }

        Post::where('id', $commentId)->delete();
        Post::where('id', $postId)->increment('comment_count', -1);
        Redis::pipeline(function ($pipe) use ($postId, $commentId, $userId)
        {
            if ($pipe->EXISTS('post_'.$postId))
            {
                $pipe->HINCRBYFLOAT('post_'.$postId, 'comment_count', -1);
            }
            $pipe->LREM('post_'.$postId.'_commentIds', 1, $commentId);
            $pipe->LREM('user_'.$userId.'_replyPostIds', 1, $commentId);
        });

        return $this->resNoContent();
    }
}
