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
use Illuminate\Support\Facades\Cache;

class PostRepository
{
    public function item($id)
    {
        $post = Cache::remember('post_'.$id, config('cache.ttl'), function () use ($id)
        {
            $data = Post::where('id', $id)->first();
            $data['images'] = PostImages::where('post_id', $id)
                ->orderBy('created_at', 'asc')
                ->pluck('src');
            $data['comments'] = $this->comments($id, 1);

            return $data;
        });

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

    public function comments($id, $page)
    {
        return Cache::remember('post_'.$id.'_comments_'.$page, config('cache.ttl'), function () use ($id, $page)
        {
            return Post::whereRaw('posts.parent_id = ? and posts.id <> ?', [$id, $id])
                ->orderBy('posts.id', 'asc')
                ->take(10)
                ->skip(($page - 1) * 10)
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
                )
                ->get();
        });
    }
}