<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CommitRequest;
use App\Http\Requests\Post\CreateRequest;
use App\Http\Requests\Post\ReplyRequest;
use App\Models\Post;
use App\Repositories\BangumiRepository;
use App\Repositories\PostRepository;
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
        $repository = new PostRepository();

        $id = $repository->create([
            'title' => Purifier::clean($request->get('title')),
            'content' => Purifier::clean($request->get('content')),
            'desc' => Purifier::clean($request->get('desc')),
            'bangumi_id' => $bangumiId,
            'user_id' => $user->id,
            'target_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now
        ], $request->get('images'));

        Redis::ZADD($repository->bangumiListCacheKey($bangumiId), $now->timestamp, $id);

        return $this->resOK($id);
    }

    public function show(Request $request, $id)
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($id);
        if (is_null($post))
        {
            return $this->resErr(['不存在的帖子']);
        }
        if ($post['parent_id'] !== '0')
        {
            return $this->resErr(['不是主题帖']);
        }
        // $user = $this->getAuthUser();
        $page = intval($request->get('page')) ?: 1;
        $take = intval($request->get('take')) ?: 10;
        $only = intval($request->get('only')) ?: 0;
        $data = $postRepository->getPostIds($id, $page, $take, $only ? $post['user_id'] : false);

        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($post['bangumi_id']);

        $list = $postRepository->list($data['ids']);
        if ($page === 1)
        {
            array_unshift($list, $post);
        }

        return $this->resOK([
            'post' => $post,
            'list' => $list,
            'bangumi' => $bangumi,
            'total' => $data['total']
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

        $newId = $repository->create([
            'content' => Purifier::clean($request->get('content')),
            'parent_id' => $id,
            'user_id' => $user->id,
            'target_user_id' => $request->get('targetUserId'),
            'created_at' => $now,
            'updated_at' => $now
        ], $request->get('images'));

        Post::where('id', $id)->increment('comment_count');
        $cacheKey = $repository->bangumiListCacheKey($request->get('bangumiId'));
        Redis::pipeline(function ($pipe) use ($id, $cacheKey, $now, $newId)
        {
            if ($pipe->EXISTS('post_'.$id))
            {
                $pipe->HINCRBYFLOAT('post_'.$id, 'comment_count', 1);
                $pipe->HSET('post_'.$id, 'updated_at', $now->toDateTimeString());
            }
            $pipe->RPUSH('post_'.$id.'_ids', $newId);
            $pipe->ZADD($cacheKey, $now->timestamp, $id);
        });

        return $this->resOK();
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

        $newId = $repository->create([
            'content' => Purifier::clean($request->get('content')),
            'parent_id' => $id,
            'user_id' => $user->id,
            'target_user_id' => $request->get('targetUserId'),
            'created_at' => $now,
            'updated_at' => $now
        ], []);

        Post::where('id', $id)->increment('comment_count');
        Redis::pipeline(function ($pipe) use ($id, $now, $newId)
        {
            if ($pipe->EXISTS('post_'.$id))
            {
                $pipe->HINCRBYFLOAT('post_'.$id, 'comment_count', 1);
            }
            $pipe->ZADD('post_'.$id.'_commentIds', $now->timestamp, $newId);
        });

        return $this->resOK($repository->comment($id, $newId));
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

        return $this->resOK($data);
    }

    public function nice($id)
    {
        // toggle 操作
    }

    public function delete($id)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['未登录的用户'], 401);
        }

        $postRepository = new PostRepository();
        $post = $postRepository->item($id);

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

        $postRepository->deletePost($id, $post['parent_id'], $state, $post['bangumi_id']);

        return $this->resOK();
    }
}
