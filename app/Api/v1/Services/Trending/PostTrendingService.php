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
use App\Api\V1\Transformers\PostTransformer;
use App\Models\Post;
use Carbon\Carbon;

class PostTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($visitorId = 0, $bangumiId = 0)
    {
        parent::__construct('posts', $bangumiId);

        $this->visitorId = $visitorId;
        $this->bangumiId = $bangumiId;
    }

    public function computeNewsIds()
    {
        return Post::where('state', 0)
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
        return Post::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query
                    ->where('bangumi_id', $this->bangumiId)
                    ->whereNull('top_at');
            })
            ->orderBy('updated_at', 'desc')
            ->latest()
            ->take(100)
            ->pluck('updated_at', 'id');
    }

    public function computeHotIds()
    {
        $ids = Post::where('created_at', '>', Carbon::now()->addDays(-30))
            ->when($this->bangumiId, function ($query)
            {
                return $query
                    ->where('bangumi_id', $this->bangumiId)
                    ->whereNull('top_at');
            })
            ->pluck('id');

        $postRepository = new PostRepository();
        $postLikeService = new PostLikeService();
        $postViewCounter = new PostViewCounter();
        $postCommentService = new PostCommentService();

        $list = $postRepository->list($ids);

        $result = [];
        // https://segmentfault.com/a/1190000004253816
        foreach ($list as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $postId = $item['id'];
            $likeCount = $postLikeService->total($postId);
            $viewCount = $postViewCounter->get($postId);
            $commentCount = $postCommentService->getCommentCount($postId);

            $result[$postId] = (
                    $likeCount +
                    ($viewCount && log($viewCount, 10) * 4) +
                    ($commentCount && log($commentCount, M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.3);
        }

        return $result;
    }

    protected function getListByIds($ids)
    {
        $postRepository = new PostRepository();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();

        $posts = $postRepository->list($ids);
        $result = [];

        foreach ($posts as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $user = $userRepository->item($item['user_id']);
            if (is_null($user))
            {
                continue;
            }

            $bangumi = $bangumiRepository->item($item['bangumi_id']);
            if (is_null($bangumi))
            {
                continue;
            }

            $item['user'] = $user;
            $item['bangumi'] = $bangumi;
            $item['liked'] = false;
            $item['commented'] = false;
            $item['marked'] = false;

            $result[] = $item;
        }

        if (empty($result))
        {
            return [];
        }

        $postLikeService = new PostLikeService();
        $postViewCounter = new PostViewCounter();
        $postCommentService = new PostCommentService();
        $postMarkService = new PostMarkService();
        $postTransformer = new PostTransformer();

//        $result = $postLikeService->batchCheck($result, $this->visitorId, 'liked');
        $result = $postLikeService->batchTotal($result, 'like_count');
//        $result = $postMarkService->batchCheck($result, $this->visitorId, 'marked');
        $result = $postMarkService->batchTotal($result, 'mark_count');
//        $result = $postCommentService->batchCheckCommented($result, $this->visitorId);
        $result = $postCommentService->batchGetCommentCount($result);
        $result = $postViewCounter->batchGet($result, 'view_count');

        return $postTransformer->trending($result);
    }
}