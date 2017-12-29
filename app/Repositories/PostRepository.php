<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/21
 * Time: 下午8:50
 */

namespace App\Repositories;


use App\Models\Post;
use App\Models\PostImages;
use Carbon\Carbon;

class PostRepository extends Repository
{
    private $userRepository;

    public function bangumiListCacheKey($bangumiId, $listType = 'new')
    {
        return 'bangumi_'.$bangumiId.'_posts_'.$listType.'_ids';
    }

    public function create($data, $images)
    {
        $now = Carbon::now();
        $newId = Post::insertGetId($data);

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

        return $newId;
    }

    public function item($id)
    {
        $post = $this->RedisHash('post_'.$id, function () use ($id)
        {
            return Post::find($id)->toArray();
        });

        if (is_null($post))
        {
            return null;
        }

        $post['images'] = $this->RedisList('post_'.$id.'_images', function () use ($id)
        {
            return PostImages::where('post_id', $id)
                ->orderBy('created_at', 'asc')
                ->pluck('src');
        });

        if (is_null($this->userRepository))
        {
            $this->userRepository = new UserRepository();
        }

        $post['user'] = $this->userRepository->item($post['user_id']);

        $post['comments'] = $this->comments($id);

        return $post;
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->item($id);
        }
        return $result;
    }

    public function comments($postId, $seenIds = [])
    {
        $cache = $this->RedisSort('post_'.$postId.'_commentIds', function () use ($postId)
        {
            return Post::where('parent_id', $postId)
                ->pluck('created_at', 'id');

        }, true);

        if (empty($cache))
        {
            return [];
        }

        $ids = array_slice(array_diff($cache, $seenIds), 0, 10);
        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->comment($postId, $id);
        }
        return $result;
    }

    public function getPostIds($id, $page, $take)
    {
        $start = $page === 1 ? 1 : ($page - 1) * $take;
        $stop = $page === 1 ? $take - 1 : $page * $take;
        return $this->RedisList('post_'.$id.'_ids', function () use ($id)
        {
            return Post::where('parent_id', $id)
                ->orderBy('id', 'asc')
                ->pluck('id');

        }, $start, $stop);
    }

    public function comment($postId, $commentId)
    {
        return $this->RedisHash('post_'.$postId.'_comment_'.$commentId, function () use ($commentId)
        {
            return Post::where('posts.id', $commentId)
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
        });
    }
}