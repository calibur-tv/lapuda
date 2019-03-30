<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/21
 * Time: 下午8:50
 */

namespace App\Api\V1\Repositories;


use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Tag\PostTagService;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Services\Toggle\Post\PostRewardService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Api\V1\Services\UserLevel;
use App\Models\Image;
use App\Models\Post;
use App\Models\PostImages;
use App\Models\Score;
use App\Services\BaiduSearch\BaiduPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class PostRepository extends Repository
{
    public function item($id, $isShow = false)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->Cache($this->itemCacheKey($id), function () use ($id)
        {
            $post = Post
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($post))
            {
                return null;
            }

            $post = $post->toArray();

            $images = PostImages
                ::where('post_id', $id)
                ->where('comment_id', 0)
                ->orderBy('created_at', 'ASC')
                ->select('url', 'width', 'height', 'size', 'type')
                ->get()
                ->toArray();

            $post['images'] = $images;

            $postTagService = new PostTagService();
            $post['tags'] = $postTagService->tags($id);

            return $post;
        });

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
        }

        return $result;
    }

    public function create($data, $images, $tags)
    {
        $newId = Post::insertGetId($data);

        if ($tags)
        {
            $postTagService = new PostTagService();
            $postTagService->update($newId, $tags);
        }

        $this->savePostImage($newId, 0, $images);
        $job = (new \App\Jobs\Trial\Post\Create($newId));
        dispatch($job);

        return $newId;
    }

    public function savePostImage($postId, $commentId, $images)
    {
        // 同时存 postId 和 commentId
        if (!empty($images))
        {
            $arr = [];
            $now = Carbon::now();

            foreach ($images as $item)
            {
                $arr[] = [
                    'post_id' => $postId,
                    'comment_id' => $commentId,
                    'url' => $this->convertImagePath($item['url']),
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

            // 更新帖子图片预览的缓存
            Redis::DEL('post_'.$postId.'_preview_images_1');
            Redis::DEL('post_'.$postId.'_preview_images_0');
        }
    }

    public function previewImages($id, $masterId, $onlySeeMaster)
    {
        return $this->Cache('post_'.$id.'_preview_images_' . $onlySeeMaster, function () use ($id, $masterId, $onlySeeMaster)
        {
            $postCommentService = new PostCommentService();

            $ids = $onlySeeMaster
                ? $postCommentService->getAuthorMainCommentIds($id, $masterId)
                : $postCommentService->getMainCommentIds($id);
            array_unshift($ids, 0);

            return PostImages
                ::where('post_id', $id)
                ->whereIn('comment_id', $ids)
                ->orderBy('created_at', 'asc')
                ->select('url', 'width', 'height', 'size', 'type')
                ->get()
                ->toArray();
        });
    }

    public function createProcess($id, $state = 0, $publish = true)
    {
        $post = $this->item($id, true);

        if ($state)
        {
            DB::table('posts')
                ->where('id', $id)
                ->update([
                    'state' => $state
                ]);
        }

        $postTrendingService = new PostTrendingService($post['bangumi_id'], $post['user_id']);
        $bangumiManager = new BangumiManager();
        $addTohomepage = $bangumiManager->isAManager($post['user_id']);
        if ($publish)
        {
            $postTrendingService->create($id, true, $addTohomepage);
        }
        else
        {
            $postTrendingService->update($id, $addTohomepage);
        }

        $baiduPush = new BaiduPush();
        $baiduPush->trending('post');
        $baiduPush->bangumi($post['bangumi_id'], 'post');

        $this->migrateSearchIndex('C', $id, false);
    }

    public function updateProcess($id)
    {
        $this->migrateSearchIndex('U', $id, false);
    }

    public function deleteProcess($id, $state = 0)
    {
        $post = $this->item($id, true);
        $userId = $post['user_id'];

        DB::table('posts')
            ->where('id', $id)
            ->update([
                'state' => $state,
                'deleted_at' => Carbon::now()
            ]);

        if ($state === 0 || $post['created_at'] !== $post['updated_at'])
        {
            $postTrendingService = new PostTrendingService($post['bangumi_id'], $userId);
            $postTrendingService->delete($id);

            $job = (new \App\Jobs\Search\Index('D', 'post', $id));
            dispatch($job);
        }

        Redis::DEL($this->itemCacheKey($id));

        $postRewardService = new PostRewardService();
        $postRewardService->cancel($id);

        $userLevel = new UserLevel();
        $exp = $userLevel->change($userId, -4, $post['desc']);
        if ($post['is_creator'])
        {
            $total = $postRewardService->total($id);
            $cancelEXP1 = $total * -3;
            $exp += $cancelEXP1;
        }
        else
        {
            $postLikeService = new PostLikeService();
            $total = $postLikeService->total($id);
            $cancelEXP1 = $total * -2;
            $exp += $cancelEXP1;
        }
        $postMarkService = new PostMarkService();
        $total = $postMarkService->total($id);
        $cancelEXP2 = $total * -2;
        $exp += $cancelEXP2;
        $userLevel->change($userId, $cancelEXP1 + $cancelEXP2, false);

        return $exp;
    }

    public function recoverProcess($id)
    {
        $post = $this->item($id, true);

        $isDeleted = $post['deleted_at'];
        $postTrendingService = new PostTrendingService($post['bangumi_id'], $post['user_id']);
        $bangumiManager = new BangumiManager();
        $addTohomepage = $bangumiManager->isAManager($post['user_id']);

        if ($isDeleted)
        {
            $postTrendingService->create($id, true, $addTohomepage);

            $this->migrateSearchIndex('C', $id, false);
        }

        DB::table('posts')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => null
            ]);

        if ($post['state'])
        {
            if (!$isDeleted)
            {
                $postTrendingService->update($id, $addTohomepage);
            }
            Redis::DEL($this->itemCacheKey($id));
        }
    }

    public function itemCacheKey($id)
    {
        return 'post_' . $id;
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $post = $this->item($id);
        $content = $post['title'] . '|' . $post['desc'];

        $job = (new \App\Jobs\Search\Index($type, 'post', $id, $content));
        dispatch($job);
    }

    public function applyComment($postId, $commentId, $images)
    {
        $this->savePostImage($postId, $commentId, $images);
    }

    public function listByFlowStatus($flowStatus, $limit, $reviewer = 0)
    {
        return Post
            ::where('flow_status', $flowStatus)
            ->where('reviewer_id', $reviewer)
            ->orderBy('id', 'desc')
            ->take($limit)
            ->pluck('id')
            ->toArray();
    }

    public function mixinFlowIds($seenIds, $take, $bangumiId = 0)
    {
        $ids = $this->RedisSort($this->mixinFlowIdsCacheKey($bangumiId), function () use ($bangumiId)
        {
            $begin = Carbon::now()->addDays(-30);
            $post = Post
                ::where('state', 0)
                ->where('updated_at', '>', $begin)
                ->when($bangumiId, function ($query) use ($bangumiId)
                {
                    return $query
                        ->where('bangumi_id', $bangumiId)
                        ->whereNull('top_at');
                })
                ->orderBy('updated_at', 'desc')
                ->pluck('updated_at', 'id');

            $image = Image
                ::where('state', 0)
                ->where('updated_at', '>', $begin)
                ->when($bangumiId, function ($query) use ($bangumiId)
                {
                    return $query->where('bangumi_id', $bangumiId);
                })
                ->where('is_album', 0)
                ->orWhere($bangumiId ? [
                    ['image_ids', '<>', null],
                    ['is_cartoon', 0],
                    ['bangumi_id', $bangumiId]
                ] : [
                    ['image_ids', '<>', null],
                    ['is_cartoon', 0]
                ])
                ->orderBy('updated_at', 'desc')
                ->pluck('updated_at', 'id');

            $score = Score
                ::where('state', 0)
                ->when($bangumiId, function ($query) use ($bangumiId)
                {
                    return $query->where('bangumi_id', $bangumiId);
                })
                ->whereNotNull('published_at')
                ->orderBy('updated_at', 'desc')
                ->pluck('updated_at', 'id');

            $result = [];
            foreach ($post as $id => $time)
            {
                $result["p-{$id}"] = $time;
            }

            foreach ($image as $id => $time)
            {
                $result["i-{$id}"] = $time;
            }

            foreach ($score as $id => $time)
            {
                $result["s-{$id}"] = $time;
            }

            return $result;
        }, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function mixinFlowIdsCacheKey($bangumi_id = 0)
    {
        return "mixin_flow_list_bangumi_{$bangumi_id}_ids";
    }
}