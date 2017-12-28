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
use Illuminate\Support\Facades\Redis;

class PostRepository extends Repository
{
    private $userRepository;

    public function bangumiListCacheKey($bangumiId, $listType = 'new')
    {
        return 'bangumi_'.$bangumiId.'_posts_'.$listType.'_ids';
    }

    public function item($id, $user)
    {
        $cacheKey = 'post_'.$id;
        $post = $this->RedisHash($cacheKey, function () use ($id)
        {
            return Post::where('id', $id)->first();
        });

        $post['images'] = $this->RedisList($cacheKey.'_images', function () use ($id)
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

        return $this->transform($post, $user);
    }

    public function list($ids, $user)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->item($id, $user);
        }
        return $result;
    }

    public function comments($postId, $seenIds = [])
    {
        $cache = $this->RedisSort('post_'.$postId.'_commentIds', function () use ($postId)
        {
            return Post::whereRaw('parent_id = ? and id <> ?', [$postId, $postId])
                ->pluck('id', 'created_at');
        });

        $ids = array_slice(array_diff($cache, $seenIds), 0, 10);
        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->comment($postId, $id);
        }
        return $result;
    }

    private function comment($postId, $commentId)
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

    private function transform($post, $currentUser)
    {
        $post['isMe'] = is_null($currentUser) ? false : $post['user_id'] === $currentUser->id;

        return $post;
    }
}