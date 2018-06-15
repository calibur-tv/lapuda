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
use App\Api\V1\Services\Counter\Post\PostReplyCounter;
use App\Api\V1\Services\Counter\Post\PostViewCounter;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Transformers\PostTransformer;
use App\Models\Post;
use Carbon\Carbon;

class PostTrendingService extends TrendingService
{
    protected $postRepository;
    protected $userRepository;
    protected $bangumiRepository;

    protected $transformer;

    protected $likeService;
    protected $markService;
    protected $commentService;

    protected $replyCounter;
    protected $viewCounter;

    protected $visitorId;

    public function __construct($visitorId = 0)
    {
        parent::__construct('posts');

        $this->visitorId = $visitorId;

        $this->postRepository = new PostRepository();
        $this->userRepository = new UserRepository();
        $this->bangumiRepository = new BangumiRepository();

        $this->transformer = new PostTransformer();

        $this->likeService = new PostLikeService();
        $this->markService = new PostMarkService();
        $this->commentService = new PostCommentService();

        $this->replyCounter = new PostReplyCounter();
        $this->viewCounter = new PostViewCounter();
    }

    public function news($minId, $take)
    {
        $idsObject = $this->getNewsIds($minId, $take);
        $list = $this->getPostsByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function active($seenIds, $take)
    {
        $idsObject = $this->getActiveIds($seenIds, $take);
        $list = $this->getPostsByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function hot($seenIds, $take)
    {
        $idsObject = $this->getHotIds($seenIds, $take);
        $list = $this->getPostsByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function computeNewsIds()
    {
        return Post::whereIn('state', [3, 7])
            ->orderBy('created_at', 'desc')
            ->latest()
            ->take(100)
            ->pluck('id');
    }

    public function computeActiveIds()
    {
        return Post::whereIn('state', [3, 7])
            ->orderBy('updated_at', 'desc')
            ->latest()
            ->take(100)
            ->pluck('updated_at', 'id');
    }

    public function computeHotIds()
    {
        $ids = Post::where('created_at', '>', Carbon::now()->addDays(-30))
            ->pluck('id');

        $list = $this->postRepository->list($ids);
        $result = [];
        // https://segmentfault.com/a/1190000004253816
        foreach ($list as $item)
        {
            if (is_null($item))
            {
                continue;
            }
            $result[$item['id']] = (
                    $item['like_count'] +
                    (intval($item['view_count']) && log($item['view_count'], 10) * 4) +
                    (intval($item['comment_count']) && log($item['comment_count'], M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.3);
        }

        return $result;
    }

    protected function getPostsByIds($ids)
    {
        $posts = $this->postRepository->list($ids);

        $result = [];
        foreach ($posts as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $user = $this->userRepository->item($item['user_id']);
            if (is_null($user))
            {
                continue;
            }

            $bangumi = $this->bangumiRepository->item($item['bangumi_id']);
            if (is_null($bangumi))
            {
                continue;
            }

            $item['user'] = $user;
            $item['bangumi'] = $bangumi;

            $result[] = $item;
        }

        $result = $this->likeService->batchCheck($result, $this->visitorId, 'liked');
        $result = $this->likeService->batchTotal($result, 'like_count');
        $result = $this->markService->batchCheck($result, $this->visitorId, 'marked');
        $result = $this->markService->batchTotal($result, 'mark_count');
        $result = $this->commentService->batchCheck($result, $this->visitorId, 'commented');
        $result = $this->replyCounter->batchGet($result, 'comment_count');
        $result = $this->viewCounter->batchGet($result, 'view_count');

        return $this->transformer->trending($result);
    }
}