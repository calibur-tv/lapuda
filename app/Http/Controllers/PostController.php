<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CommitRequest;
use App\Http\Requests\Post\CreateRequest;
use App\Http\Requests\Post\ReplyRequest;
use App\Models\Post;
use App\Models\PostImages;
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

        $id = Post::insertGetId([
            'title' => Purifier::clean($request->get('title')),
            'content' => Purifier::clean($request->get('content')),
            'desc' => Purifier::clean($request->get('desc')),
            'bangumi_id' => $bangumiId,
            'user_id' => $user->id,
            'target_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        Post::where('id', $id)->update([
            'parent_id' => $id,
            'updated_at' => $now
        ]);

        $images = $request->get('images');
        if (!empty($images))
        {
            $arr = [];

            foreach ($images as $item)
            {
                $arr[] = [
                    'post_id' => $id,
                    'src' => $item,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            PostImages::insert($arr);
        }

        $repository = new PostRepository();
        $cacheKey = $repository->bangumiListCacheKey($bangumiId);
        Redis::LPUSH($cacheKey, $id);

        return $this->resOK($id);
    }

    public function show(Request $request, $id)
    {
        $user = $this->getAuthUser();
        $page = $request->get('page') ?: 1;
        $take = $request->get('take') ?: 10;

        $ids = Post::where('parent_id', $id)
            ->orderBy('id', 'asc')
            ->skip(($page - 1) * $take)
            ->take($take)
            ->pluck('id');

        $bangumiId = Post::where('id', $id)
            ->pluck('bangumi_id')
            ->first();
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($bangumiId);

        $postRepository = new PostRepository();
        $list = $postRepository->list($ids, $user);

        if ($page === 1) {
            $post = $list[0];
        } else {
            $post = $postRepository->item($id, $user);
        }

        return $this->resOK([
            'post' => $post,
            'list' => $list,
            'bangumi' => $bangumi
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

        $newId = Post::insertGetId([
            'content' => Purifier::clean($request->get('content')),
            'parent_id' => $id,
            'user_id' => $user->id,
            'target_user_id' => $request->get('targetUserId'),
            'created_at' => $now,
            'updated_at' => $now
        ]);

        $images = $request->get('images');
        if (!empty($images))
        {
            $arr = [];

            foreach ($images as $item)
            {
                $arr[] = [
                    'post_id' => $newId,
                    'src' => $item,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            PostImages::insert($arr);
        }

        Post::where('id', $id)->increment('comment_count');

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

        $newId = Post::insertGetId([
            'content' => Purifier::clean($request->get('content')),
            'parent_id' => $id,
            'user_id' => $user->id,
            'target_user_id' => $request->get('targetUserId'),
            'created_at' => $now,
            'updated_at' => $now
        ]);

        Post::where('id', $id)->increment('comment_count');

        $post = Post::where('posts.id', $newId)
            ->leftJoin('users AS from', 'from.id', '=', 'posts.user_id')
            ->leftJoin('users AS to', 'to.id', '=', 'posts.target_user_id')
            ->select(
                'posts.id',
                'posts.content',
                'posts.created_at',
                'posts.user_id',
                'from.nickname AS from_user_name',
                'from.zone AS from_user_zone',
                'from.avatar AS from_user_avatar',
                'to.nickname AS to_user_name',
                'to.zone AS to_user_zone'
            )->first();

        return $this->resOK($post);
    }

    public function comments(Request $request, $id)
    {
        $page = $request->get('page') ?: 1;

        $repository = new PostRepository();
        $data = $repository->comments($id, $page);

        return $this->resOK($data);
    }

    public function nice($id)
    {
        // toggle 操作
    }

    public function delete($id)
    {
        // 软删除，并删除缓存中的 item
    }
}
