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
use App\Models\PostLike;
use App\Models\PostMark;
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
                    'src' => $item['key'],
                    'size' => intval($item['size']),
                    'width' => intval($item['width']),
                    'height' => intval($item['height']),
                    'origin_url' => '',
                    'type' => $item['type'],
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
            $post = Post::find($id);

            if (is_null($post))
            {
                return null;
            }

            return $post->toArray();
        });

        if (is_null($post))
        {
            return null;
        }

        $post['images'] = $this->images($id);

        if (is_null($this->userRepository))
        {
            $this->userRepository = new UserRepository();
        }

        $post['user'] = $this->userRepository->item($post['user_id']);

        $post['comments'] = intval($post['parent_id']) === 0 ? [] : $this->comments($id);

        if (intval($post['bangumi_id']) !== 0)
        {
            if (is_null($this->bangumiRepository))
            {
                $this->bangumiRepository = new BangumiRepository();
            }

            $post['bangumi'] = $this->bangumiRepository->item($post['bangumi_id']);
            $post['likeUsers'] = $this->likeUsers($id);
        }

        if ($post['parent_id'] === '0')
        {
            $post['previewImages'] = $this->previewImages($post['id'], false);
        }

        return $post;
    }

    public function likeUsers($postId, $seenIds = [], $take = 10)
    {
        $cache = $this->RedisList('post_'.$postId.'_likeUserIds', function () use ($postId)
        {
            return PostLike::where('post_id', $postId)
                ->orderBy('id', 'DESC')
                ->pluck('user_id');
        });

        if (empty($cache))
        {
            return [];
        }

        if (is_null($this->userRepository))
        {
            $this->userRepository = new UserRepository();
        }

        $ids = array_slice(array_diff($cache, $seenIds), 0, $take);
        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->userRepository->item($id);
        }
        return $result;
    }

    public function images($postId)
    {
        return $this->RedisList('post_'.$postId.'_images', function () use ($postId)
        {
            $images = PostImages::where('post_id', $postId)
                ->orderBy('created_at', 'ASC')
                ->select('src', 'width', 'height')
                ->get()
                ->toArray();

            $result = [];

            foreach ($images as $item)
            {
                $result[] = $item['width'] . '-' . $item['height'] . '|' . $item['src'];
            }

            return $result;
        });
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->item($id);
            if ($item) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function comments($postId, $seenIds = [])
    {
        $cache = $this->RedisList('post_'.$postId.'_commentIds', function () use ($postId)
        {
            return Post::where('parent_id', $postId)
                ->orderBy('created_at', 'ASC')
                ->pluck('id')
                ->toArray();
        });

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

    public function getPostIds($id, $onlySeeMaster)
    {
        if ($onlySeeMaster)
        {
            return $this->RedisList('post_'.$id.'_ids_only', function () use ($id, $onlySeeMaster)
            {
                return Post::whereRaw('parent_id = ? and user_id = ?', [$id, $onlySeeMaster])
                    ->orderBy('id', 'asc')
                    ->pluck('id');
            });
        }

        return $this->RedisList('post_'.$id.'_ids', function () use ($id)
        {
            return Post::where('parent_id', $id)
                ->orderBy('id', 'asc')
                ->pluck('id');
        });
    }

    public function previewImages($id, $onlySeeMaster)
    {
        return $this->RedisList('post_'.$id.'_previewImages', function () use ($id, $onlySeeMaster)
        {
            $ids = $this->getPostIds($id, $onlySeeMaster);

            $ids[] = $id;

            $images = PostImages::whereIn('post_id', $ids)
                ->orderBy('created_at', 'asc')
                ->select('src', 'width', 'height')
                ->get()
                ->toArray();

            $result = [];

            foreach ($images as $item)
            {
                $result[] = $item['width'] . '-' . $item['height'] . '|' . $item['src'];
            }

            return $result;
        });
    }

    public function comment($postId, $commentId)
    {
        return $this->RedisHash('post_'.$postId.'_comment_'.$commentId, function () use ($commentId)
        {
            $comment = Post::where('posts.id', $commentId)
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
                    'to.zone AS to_user_zone',
                    'posts.parent_id AS parent_id'
                )->first();

            if (is_null($comment))
            {
                return null;
            }

            return $comment->toArray();
        });
    }

    public function deletePost($post, $state)
    {
        $postId = $post['id'];
        $userId = $post['user_id'];
        $parentId = $post['parent_id'];
        $bangumiId = $post['bangumi_id'];
        DB::table('posts')
            ->where('id', $postId)
            ->update([
                'state' => $state,
                'deleted_at' => Carbon::now()
            ]);

        if (intval($parentId) !== 0)
        {
            /*
             * 如果是回帖，那么主题帖不会被删
             * 主题帖的回复数要 - 1 （数据库和缓存）
             * 删除主题帖的 ids （所有列表和仅楼主列表）
             */
            Post::where('id', $parentId)->increment('comment_count', -1);
            if (Redis::EXISTS('post_'.$parentId))
            {
                Redis::HINCRBYFLOAT('post_'.$parentId, 'comment_count', -1);
            }
            Redis::pipeline(function ($pipe) use ($parentId, $userId, $postId)
            {
                $pipe->LREM('user_'.$userId.'_replyPostIds', 1, $postId);
                $pipe->DEL('post_'.$parentId.'_ids');
                $pipe->DEL('post_'.$parentId.'_ids_only');
            });
        }
        else
        {
            /*
             * 删除主题帖
             * 删除 bangumi-cache-ids-list 中的这个帖子 id
             * 删除用户帖子列表的id
             * 删除最新和热门帖子下该帖子的缓存
             * 删掉主题帖的缓存
             */
            Redis::pipeline(function ($pipe) use ($bangumiId, $postId, $userId)
            {
                $pipe->LREM('user_'.$userId.'_minePostIds', 1, $postId);
                $pipe->ZREM($this->bangumiListCacheKey($bangumiId), $postId);
                $pipe->ZREM('post_new_ids', $postId);
                $pipe->ZREM('post_hot_ids', $postId);
                $pipe->DEL('post_'.$postId);
            });

            $job = (new \App\Jobs\Search\Post\Delete($postId));
            dispatch($job);

            $job = (new \App\Jobs\Push\Baidu('post/' . $postId, 'del'));
            dispatch($job);
        }
    }

    public function getNewIds($force = false)
    {
        return $this->RedisSort('post_new_ids', function ()
        {
            return Post::whereIn('state', [3, 7])
                ->where('parent_id', 0)
                ->orderBy('created_at', 'desc')
                ->latest()
                ->take(1000)
                ->pluck('created_at', 'id');

        }, true, $force);
    }

    public function getHotIds($force = false)
    {
        return $this->RedisSort('post_hot_ids', function ()
        {
            $ids = Post::whereRaw('created_at > ? and parent_id = ?', [
                Carbon::now()->addDays(-30), 0
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
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 1);
            }

            return $result;
        }, false, $force);
    }

    public function checkPostLiked($postId, $userId)
    {
        return PostLike::whereRaw('user_id = ? and post_id = ?', [$userId, $postId])->count() !== 0;
    }

    public function checkPostMarked($postId, $userId)
    {
        return PostMark::whereRaw('user_id = ? and post_id = ?', [$userId, $postId])->count() !== 0;
    }

    public function checkPostCommented($postId, $userId)
    {
        return Post::whereRaw('parent_id = ? and user_id = ?', [$postId, $userId])->count() !== 0;
    }
}