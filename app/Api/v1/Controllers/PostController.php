<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment;
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

        $bangumiRepository = new BangumiRepository();
        if (!$bangumiRepository->checkUserFollowed($userId, $bangumiId))
        {
            $bangumiRepository->toggleFollow($this->getAuthUserId(), $bangumiId);
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

        if (intval($post['parent_id']) !== 0)
        {
            return $this->resErrNotFound('不是主题帖');
        }

        $userId = $this->getAuthUserId();
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;
        $take = empty($seen) ? $take - 1 : $take;
        $only = intval($request->get('only')) ?: 0;
        $ids = $postRepository->getPostIds($id, $only ? $post['user_id'] : false);

        $replyId = $request->get('replyId') ? intval($request->get('replyId')) : 0;
        $ids = array_slice(array_diff($ids, $seen), 0, $take);

        if ($replyId && count($ids))
        {
            if (!in_array($replyId, $ids))
            {
                $ids[count($ids) - 1] = $replyId;
            }
        }

        $list = $postRepository->list($ids);

        if (empty($seen))
        {
            Post::where('id', $post['id'])->increment('view_count');
            if (Redis::EXISTS('post_'.$id))
            {
                Redis::HINCRBYFLOAT('post_'.$id, 'view_count', 1);
            }
        }

        $postTransformer = new PostTransformer();
        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $postRepository->checkPostLiked($item['id'], $userId, $item['user_id']);
        }

        $list = $postTransformer->reply($list);

        if (!empty($seen))
        {
            return $this->resOK([
                'list' => $list,
                'total' => count($ids) + 1
            ]);
        }

        $post['liked'] = $postRepository->checkPostLiked($id, $userId, $post['user_id']);
        $post['marked'] = $postRepository->checkPostMarked($id, $userId, $post['user_id']);
        $post['commented'] = $postRepository->checkPostCommented($id, $userId);

        $bangumiRepository = new BangumiRepository();
        $userRepository = new UserRepository();
        $bangumiTransformer = new BangumiTransformer();
        $userTransformer = new UserTransformer();
        $post['previewImages'] = $postRepository->previewImages($id, $only ? $post['user_id'] : false);
        $bangumi = $bangumiRepository->item($post['bangumi_id']);
        if (is_null($bangumi))
        {
            return null;
        }

        $bangumi['followed'] = $bangumiRepository->checkUserFollowed($userId, $post['bangumi_id']);

        return $this->resOK([
            'post' => $postTransformer->show($post),
            'list' => $list,
            'bangumi' => $bangumiTransformer->post($bangumi),
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
        $count = Post::withTrashed()->where('parent_id', $id)->count();

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
        Post::where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s',time())
        ]);
        $cacheKey = $repository->bangumiListCacheKey($post['bangumi_id']);
        // 更新帖子的缓存
        if (Redis::EXISTS('post_'.$id))
        {
            Redis::HINCRBYFLOAT('post_'.$id, 'comment_count', 1);
            Redis::HSET('post_'.$id, 'updated_at', $now->toDateTimeString());
        }
        // 更新帖子图片预览的缓存
        if (Redis::EXISTS('post_'.$id.'_previewImages') && !empty($images))
        {
            foreach ($images as $i => $val)
            {
                $images[$i] = config('website.image') . $val['key'];
            }
            Redis::RPUSH('post_'.$id.'_previewImages', $images);
        }
        // 更新番剧帖子列表的缓存
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

        $reply = $repository->item($newId);
        $reply['liked'] = false;
        $transformer = new PostTransformer();

        if (intval($post['user_id']) !== $userId)
        {
            $job = (new \App\Jobs\Notification\Post\Reply($newId));
            dispatch($job);
        }
        $job = (new \App\Jobs\Trial\Post\Reply($newId));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('post/' . $id, 'update'));
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
            'content' => 'required|max:100'
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

        $userId = $this->getAuthUserId();
        $targetUserId = $request->get('targetUserId');
        $commentService = new Comment('post');

        $newComment = $commentService->create([
            'content' => $request->get('content'),
            'user_id' => $userId,
            'parent_id' => $id,
            'to_user_id' => $targetUserId
        ]);

        if (is_null($newComment))
        {
            return $this->resErrServiceUnavailable();
        }

        $newId = $newComment['id'];

        Post::where('id', $id)->increment('comment_count');
        Post::where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s',time())
        ]);

        if (Redis::EXISTS('post_'.$id))
        {
            Redis::HINCRBYFLOAT('post_'.$id, 'comment_count', 1);
        }

        Redis::pipeline(function ($pipe) use ($id, $newId, $userId)
        {
            $pipe->LPUSHX('user_'.$userId.'_replyPostIds', $newId);
        });

        if (intval($targetUserId) !== 0)
        {
            $job = (new \App\Jobs\Notification\Post\Comment($newId));
            dispatch($job);
        }

        $job = (new \App\Jobs\Push\Baidu('post/' . $post['parent_id'], 'update'));
        dispatch($job);

        return $this->resCreated($newComment);
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

        $maxId = $request->get('maxId') ?: 0;

        return $this->resOK($repository->comments($id, $maxId));
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

        $liked = $postRepository->checkPostLiked($postId, $userId, $post['user_id']);

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
            $now = Carbon::now();
            $likeId = PostLike::insertGetId([
                'user_id' => $userId,
                'post_id' => $postId,
                'created_at' => $now,
                'updated_at' => $now
            ]);
            $num = 1;

            if ($isMainPost)
            {
                $job = (new \App\Jobs\Notification\Post\Like($likeId));
            }
            else
            {
                $job = (new \App\Jobs\Notification\Post\Agree($likeId));
            }
            dispatch($job);
        }

        Post::where('id', $post['id'])->increment('like_count', $num);
        if (Redis::EXISTS('post_'.$postId))
        {
            Redis::HINCRBYFLOAT('post_'.$postId, 'like_count', $num);
        }

        if ($isMainPost)
        {
            $job = (new \App\Jobs\Search\Post\Update($postId, $liked ? -10 : 10));
            dispatch($job);
        }
        else
        {
            $job = (new \App\Jobs\Search\Post\Update($postId, $num));
            dispatch($job);
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

        $marked = $postRepository->checkPostMarked($postId, $userId, $post['user_id']);
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

        $job = (new \App\Jobs\Search\Post\Update($postId, $marked ? -10 : 10));
        dispatch($job);

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

        $commentService = new Comment('post');
        $comment = $commentService->item($commentId);

        if (is_null($comment))
        {
            return $this->resErrNotFound('不存在的评论');
        }

        if (intval($postId) !== $comment['parent_id'])
        {
            return $this->resErrBad('非法的请求');
        }

        $userId = $this->getAuthUserId();
        $result = $commentService->delete($commentId, $userId);
        if (!$result)
        {
            return $this->resErrRole('权限不足');
        }

        Post::where('id', $postId)->increment('comment_count', -1);
        if (Redis::EXISTS('post_'.$postId))
        {
            Redis::HINCRBYFLOAT('post_'.$postId, 'comment_count', -1);
        }
        Redis::pipeline(function ($pipe) use ($postId, $commentId, $userId)
        {
            $pipe->LREM('user_'.$userId.'_replyPostIds', 1, $commentId);
        });

//        $post = $postRepository->item(($comment['parent_id']));
//        $job = (new \App\Jobs\Search\Post\Update($post['parent_id'], -2));
//        dispatch($job);

        return $this->resNoContent();
    }
}
