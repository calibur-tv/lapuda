<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Requests\Post\CommitRequest;
use App\Api\V1\Requests\Post\CreateRequest;
use App\Api\V1\Requests\Post\ReplyRequest;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Post;
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Models\PostLike;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware('geetest')->only([
            'create', 'reply'
        ]);
    }

    public function create(CreateRequest $request)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['未登录的用户'], 401);
        }

        $now = Carbon::now();
        $bangumiId = $request->get('bangumiId');
        $userId = $user->id;
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

        $job = (new \App\Jobs\Trial\Post($id))->onQueue('post-create');
        dispatch($job);

        return $this->resOK($id);
    }

    public function show(Request $request, $id)
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($id);
        if ($post['parent_id'] !== '0')
        {
            return $this->resErr(['不是主题帖']);
        }
        // $user = $this->getAuthUser();
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;
        $only = intval($request->get('only')) ?: 0;
        $ids = $postRepository->getPostIds($id, $only ? $post['user_id'] : false);

        $list = $postRepository->list(array_slice(array_diff($ids, $seen), 0, $take));

        Post::where('id', $post['id'])->increment('view_count');
        if (Redis::EXISTS('post_'.$id))
        {
            Redis::HINCRBYFLOAT('post_'.$id, 'view_count', 1);
        }

        $postTransformer = new PostTransformer();

        if (!empty($seen))
        {
            return $this->resOK([
                'list' => $postTransformer->reply($list),
                'total' => count($ids)
            ]);
        }

        $bangumiRepository = new BangumiRepository();
        $userRepository = new UserRepository();
        $bangumiTransformer = new BangumiTransformer();
        $userTransformer = new UserTransformer();
        $post['previewImages'] = $postRepository->previewImages($id, $only ? $post['user_id'] : false);

        return $this->resOK([
            'post' => $postTransformer->show($post),
            'list' => $postTransformer->reply($list),
            'bangumi' => $bangumiTransformer->item($bangumiRepository->item($post['bangumi_id'])),
            'user' => $userTransformer->item($userRepository->item($post['user_id'])),
            'total' => count($ids)
        ]);
    }

    public function reply(ReplyRequest $request, $id)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['未登录的用户'], 401);
        }

        $now = Carbon::now();
        $repository = new PostRepository();
        $userId = $user->id;
        $count = Post::where('parent_id', $id)->count();

        $images = $request->get('images');
        $newId = $repository->create([
            'content' => Purifier::clean($request->get('content')),
            'parent_id' => $id,
            'user_id' => $userId,
            'target_user_id' => $request->get('targetUserId'),
            'floor_count' => $count + 2,
            'created_at' => $now,
            'updated_at' => $now
        ], $images);

        Post::where('id', $id)->increment('comment_count');
        $cacheKey = $repository->bangumiListCacheKey($request->get('bangumiId'));
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
                    $images[$i] = config('website.cdn') . $val;
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

        $post = $repository->item($newId);

        return $this->resOK($post);
    }

    public function commit(CommitRequest $request, $id)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['未登录的用户'], 401);
        }

        $now = Carbon::now();
        $repository = new PostRepository();
        $userId = $user->id;

        $newId = $repository->create([
            'content' => Purifier::clean($request->get('content')),
            'parent_id' => $id,
            'user_id' => $userId,
            'target_user_id' => $request->get('targetUserId'),
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

        return $this->resOK($postTransformer->comments([$repository->comment($id, $newId)])[0]);
    }

    public function comments(Request $request, $id)
    {
        $repository = new PostRepository();
        $data = $repository->comments(
            $id,
            $request->get('seenIds')
                ? explode(',', $request->get('seenIds'))
                : []
        );

        $postTransformer = new PostTransformer();

        return $this->resOK($postTransformer->comments($data));
    }

    public function toggleLike($postId)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['未登录的用户'], 401);
        }

        $userId = $user->id;
        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);
        $postLike = PostLike::whereRaw('user_id = ? and post_id = ?'. [$userId, $post['id']])->first();
        $liked = $postLike !== null;

        if ($liked)
        {
            $postLike->delete();
            Post::where('id', $post['id'])->increment('like_count', -1);
        }
        else
        {
            PostLike::create([
                'user_id' => $userId,
                'post_id' => $postId
            ]);
            Post::where('id', $post['id'])->increment('like_count', 1);
        }

        // 如果是主题帖，要删除楼主所得的金币，但金币不返还给用户
        if ($post['parent_id'] == '0')
        {
            $userRepository = new UserRepository();
            $userRepository->toggleCoin($liked, $userId, $post['user_id'], 1);
        }

        return $this->resOK();
    }

    public function delete($postId)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['未登录的用户'], 401);
        }

        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);

        $delete = false;
        $state = 0;
        if ($post['user_id'] == $user->id)
        {
            $delete = true;
            $state = 1;
        }
        else if ($post['parent_id'] != 0)
        {
            $post = $postRepository->item($post['parent_id']);
            if ($post['user_id'] == $user->id)
            {
                $delete = true;
                $state = 2;
            }
        }

        if (!$delete)
        {
            return $this->resErr(['权限不足'], 401);
        }

        $postRepository->deletePost($postId, $post['parent_id'], $state, $post['bangumi_id']);

        return $this->resOK();
    }
}
