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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

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
            return Post::find($id);
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

        $post['comments'] = $post['parent_id'] === '0' ? [] : $this->comments($id);

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

        $ids = array_slice(array_reverse(array_diff($cache, $seenIds)), 0, 10);
        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->comment($postId, $id);
        }
        return $result;
    }

    public function getPostIds($id, $page, $take, $postMasterId)
    {
        /**
         * 因为：page = 1 的时候，不用获取 1 楼
         * 所以，当 take = 10 时：
         * page = 1 -> start = 1 end = 9
         * page = 2 -> start = 10, end = 19
         */
        $start = $page === 1 ? 1 : ($page - 1) * $take;
        $stop = $page === 1 ? $take - 1 : $start + $take - 1;

        if ($postMasterId)
        {
            $key = 'post_'.$id.'_ids_only';
            $ids = $this->RedisList($key, function () use ($id, $postMasterId)
            {
                return Post::whereRaw('parent_id = ? and user_id = ?', [$id, $postMasterId])
                    ->orderBy('id', 'asc')
                    ->pluck('id');

            }, $start, $stop);
        }
        else
        {
            $key = 'post_'.$id.'_ids';
            $ids = $this->RedisList($key, function () use ($id)
            {
                return Post::where('parent_id', $id)
                    ->orderBy('id', 'asc')
                    ->pluck('id');

            }, $start, $stop);
        }

        return [
            'ids' => $ids,
            'total' => Redis::LLEN($key)
        ];
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

    public function deletePost($postId, $parentId, $state, $bangumiId)
    {
        DB::table('posts')
            ->where('id', $postId)
            ->update([
                'state' => $state,
                'deleted_at' => Carbon::now()
            ]);

        if ($parentId != 0)
        {
            /*
             * 如果是回帖，那么主题帖不会被删
             * 主题帖的回复数要 - 1 （数据库和缓存）
             * 删除主题帖的 ids （所有列表和仅楼主列表）
             */
            Post::where('id', $parentId)->increment('comment_count', -1);
            Redis::pipeline(function ($pipe) use ($parentId)
            {
                if ($pipe->EXISTS('post_'.$parentId))
                {
                    $pipe->HINCRBYFLOAT('post_'.$parentId, 'comment_count', -1);
                }
                $pipe->DEL('post_'.$parentId.'_ids');
                $pipe->DEL('post_'.$parentId.'_ids_only');
            });
        }
        else
        {
            /*
             * 删除主题帖
             * 删除 bangumi-cache-ids-list 中的这个帖子 id
             * 删掉主题帖的缓存
             * 其它缓存自然过期
             */
            Redis::pipeline(function ($pipe) use ($bangumiId, $postId)
            {
                $pipe->ZREM($this->bangumiListCacheKey($bangumiId), $postId);
                $pipe->DEL('post_'.$postId);
            });
        }
    }
}