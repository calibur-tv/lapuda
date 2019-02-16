<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/10
 * Time: 上午9:52
 */

namespace App\Api\V1\Services\Trending;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Counter\PostViewCounter;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Services\Toggle\Post\PostRewardService;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Transformers\PostTransformer;
use App\Models\Post;
use Carbon\Carbon;

class PostTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($bangumiId = 0, $userId = 0)
    {
        parent::__construct('posts', $bangumiId, $userId);
    }

    public function computeNewsIds()
    {
        return Post
            ::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query
                    ->where('bangumi_id', $this->bangumiId)
                    ->whereNull('top_at');
            })
            ->orderBy('created_at', 'desc')
            ->latest()
            ->take(100)
            ->pluck('id');
    }

    public function computeActiveIds()
    {
        return Post
            ::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query
                    ->where('bangumi_id', $this->bangumiId)
                    ->whereNull('top_at');
            })
            ->orderBy('updated_at', 'desc')
            ->take(300)
            ->pluck('updated_at', 'id');
    }

    public function computeHotIds()
    {
        $ids = Post
            ::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query
                    ->where('bangumi_id', $this->bangumiId)
                    ->whereNull('top_at');
            })
            ->when(!$this->bangumiId, function ($query)
            {
                return $query
                    ->where('flow_status', 1);
            })
            ->pluck('id');

        $postRepository = new PostRepository();
        $postLikeService = new PostLikeService();
        $postViewCounter = new PostViewCounter();
        $postMarkService = new PostMarkService();
        $postRewardService = new PostRewardService();
        $postCommentService = new PostCommentService();

        $list = $postRepository->list($ids);

        $result = [];
        // https://segmentfault.com/a/1190000004253816
        foreach ($list as $item)
        {
            $postId = $item['id'];
            $likeCount = $postLikeService->total($postId);
            $markCount = $postMarkService->total($postId);
            $rewardCount = $postRewardService->total($postId);
            $viewCount = $postViewCounter->get($postId);
            $commentCount = $postCommentService->getCommentCount($postId);

            $result[$postId] = (
                    strtotime($item['created_at']) * 2 +
                    ($viewCount && log($viewCount, 10) * 4) +
                    ($likeCount * 2 + $markCount * 2 + $rewardCount * 3) +
                    ($commentCount && log($commentCount, M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.1);
        }

        return $result;
    }

    public function create($id, $publish = true, $addToHomepage = true)
    {
        if ($publish)
        {
            if (gettype($this->bangumiId) === 'array')
            {
                foreach ($this->bangumiId as $bid)
                {
                    // $this->ListInsertBefore($this->trendingIdsCacheKey('news', $bid), $id);
                    // $this->SortAdd($this->trendingIdsCacheKey('active', $bid), $id);
                    $this->SortAdd($this->trendingIdsCacheKey('hot', $bid), $id);
                }
            }
            else
            {
                // $this->ListInsertBefore($this->trendingIdsCacheKey('news', $this->bangumiId), $id);
                // $this->SortAdd($this->trendingIdsCacheKey('active', $this->bangumiId), $id);
                $this->SortAdd($this->trendingIdsCacheKey('hot', $this->bangumiId), $id);
            }
            // $this->ListInsertBefore($this->trendingIdsCacheKey('news', 0), $id);
            // $this->SortAdd($this->trendingIdsCacheKey('active', 0), $id);
            if ($addToHomepage)
            {
                $this->SortAdd($this->trendingIdsCacheKey('hot', 0), $id);
            }
        }
        $this->ListInsertBefore($this->trendingFlowUsersKey(), $id);
    }

    public function update($id, $addToHomepage = true)
    {
        if (gettype($this->bangumiId) === 'array')
        {
            foreach ($this->bangumiId as $bid)
            {
                // $this->SortAdd($this->trendingIdsCacheKey('active', $bid), $id);
                $this->SortAdd($this->trendingIdsCacheKey('hot', $bid), $id);
            }
        }
        else if ($this->checkCanUpdateBangumiIds($id))
        {
            // $this->SortAdd($this->trendingIdsCacheKey('active', $this->bangumiId), $id);
            $this->SortAdd($this->trendingIdsCacheKey('hot', $this->bangumiId), $id);
        }
        // $this->SortAdd($this->trendingIdsCacheKey('active', 0), $id);
        if ($addToHomepage)
        {
            $this->SortAdd($this->trendingIdsCacheKey('hot', 0), $id);
        }
    }

    public function computeUserIds()
    {
        return Post
            ::where('user_id', $this->userId)
            ->orderBy('created_at', 'desc')
            ->pluck('created_at', 'id');
    }

    public function checkCanUpdateBangumiIds($id)
    {
        $bangumiRepository = new BangumiRepository();
        $ids = $bangumiRepository->getTopPostIds($this->bangumiId);
        if (!$ids)
        {
            return true;
        }

        return !in_array($id, $ids);
    }

    public function getListByIds($ids, $flowType)
    {
        $store = new PostRepository();
        /*
        if ($flowType === 'bangumi')
        {
            $list = $store->bangumiFlow($ids);
        }
        else if ($flowType === 'user')
        {
            $list = $store->userFlow($ids);
        }
        else
        {
            $list = $store->trendingFlow($ids);
        }
        */
        $list = $store->trendingFlow($ids);
        if (empty($list))
        {
            return [];
        }

        $likeService = new PostLikeService();
        $rewardService = new PostRewardService();
        $markService = new PostMarkService();
        $commentService = new PostCommentService();

        $list = $commentService->batchGetCommentCount($list);
        $list = $likeService->batchTotal($list, 'like_count');
        $list = $markService->batchTotal($list, 'mark_count');
        foreach ($list as $i => $item)
        {
            if ($item['is_creator'])
            {
                $list[$i]['reward_count'] = $rewardService->total($item['id']);
            }
            else
            {
                $list[$i]['reward_count'] = 0;
            }
        }

        $transformer = new PostTransformer();
        /*
        if ($flowType === 'bangumi')
        {
            return $transformer->bangumiFlow($list);
        }
        else if ($flowType === 'user')
        {
            return $transformer->userFlow($list);
        }
        */

        return $transformer->trendingFlow($list);
    }
}