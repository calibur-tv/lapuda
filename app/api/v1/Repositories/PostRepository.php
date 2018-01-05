<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/21
 * Time: 下午8:50
 */

namespace App\Api\V1\Repositories;


use App\Models\Post;
use App\Models\PostImages;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class PostRepository extends Repository
{
    private $userRepository;
    private $bangumiRepository;

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
            return Post::findOrFail($id)->toArray();
        });

        $post['images'] = $this->RedisList('post_'.$id.'_images', function () use ($id)
        {
            return PostImages::where('post_id', $id)
                ->orderBy('created_at', 'asc')
                ->pluck('src')
                ->toArray();
        });

        if (is_null($this->userRepository))
        {
            $this->userRepository = new UserRepository();
        }

        $post['user'] = $this->userRepository->item($post['user_id']);

        $post['comments'] = $post['parent_id'] === '0' ? [] : $this->comments($id);

        if ($post['bangumi_id'] !== '0')
        {
            if (is_null($this->bangumiRepository))
            {
                $this->bangumiRepository = new BangumiRepository();
            }

            $post['bangumi'] = $this->bangumiRepository->item($post['bangumi_id']);
        }

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

        }, true);;

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

    public function getPostIds($id, $page, $take, $onlySeeMaster)
    {
        /**
         * page = 1 的时候，不用获取 1 楼
         */
        $start = ($page - 1) * $take;
        $count = $page === 1 ? $take - 1 : $take;

        if ($onlySeeMaster)
        {
            $key = 'post_'.$id.'_ids_only';
            $ids = $this->RedisList($key, function () use ($id, $onlySeeMaster)
            {
                return Post::whereRaw('parent_id = ? and user_id = ?', [$id, $onlySeeMaster])
                    ->orderBy('id', 'asc')
                    ->pluck('id');

            }, $start, $count);
        }
        else
        {
            $key = 'post_'.$id.'_ids';
            $ids = $this->RedisList($key, function () use ($id)
            {
                return Post::where('parent_id', $id)
                    ->orderBy('id', 'asc')
                    ->pluck('id');

            }, $start, $count);
        }

        return [
            'ids' => $ids,
            'total' => Redis::LLEN($key)
        ];
    }

    public function images($id, $onlySeeMaster)
    {
        return $this->RedisList('post_'.$id.'_previewImages', function () use ($id, $onlySeeMaster)
        {
            $ids = $this->getPostIds($id, 1, 0, $onlySeeMaster)['ids'];

            return PostImages::whereIn('post_id', $ids)
                ->orderBy('created_at', 'asc')
                ->pluck('src')
                ->toArray();
        });
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
                    'posts.user_id AS from_user_id',
                    'from.nickname AS from_user_name',
                    'from.zone AS from_user_zone',
                    'from.avatar AS from_user_avatar',
                    'to.nickname AS to_user_name',
                    'to.zone AS to_user_zone'
                )->first()->toArray();
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

    public function getNewIds()
    {
        return $this->RedisSort('post_new_ids', function ()
        {
            return Post::whereIn('state', [3, 7])
                ->orderBy('created_at', 'desc')
                ->latest()
                ->take(1000)
                ->pluck('created_at', 'id');

        }, true);
    }

    public function getHotIds()
    {
        return $this->RedisSort('post_hot_ids', function ()
        {
            $ids = Post::whereRaw('created_at > ? and parent_id = ?', [
                Carbon::now()->addDays(-7), 0
            ])->pluck('id');

            $list = $this->list($ids);
            $result = [];
            // https://segmentfault.com/a/1190000004253816
            foreach ($list as $item)
            {
                $result[$item['id']] = (
                    $item['like_count'] +
                    (intval($item['view_count']) && log($item['view_count'], 10) * 4) +
                    (intval($item['comment_count']) && log($item['comment_count'], M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.5);
            }

            return $result;
        });
    }
}