<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Comment\ScoreCommentService;
use App\Api\V1\Services\Counter\Stats\TotalAnswerCount;
use App\Api\V1\Services\Counter\Stats\TotalBangumiCount;
use App\Api\V1\Services\Counter\Stats\TotalImageAlbumCount;
use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Api\V1\Services\Counter\Stats\TotalPostCount;
use App\Api\V1\Services\Counter\Stats\TotalQuestionCount;
use App\Api\V1\Services\Counter\Stats\TotalScoreCount;
use App\Api\V1\Services\Tag\IndexTagService;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Toggle\Image\ImageMarkService;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Services\Toggle\Score\ScoreLikeService;
use App\Api\V1\Services\Toggle\Score\ScoreMarkService;
use App\Api\V1\Services\Trending\AnswerTrendingService;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Api\V1\Services\Trending\QuestionTrendingService;
use App\Api\V1\Services\Trending\CartoonRoleTrendingService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\ImageTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\ScoreTransformer;
use App\Api\V1\Transformers\UserTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @Resource("信息流相关接口")
 */
class TrendingController extends Controller
{
    /**
     * 获取信息流列表
     *
     * > 调用方法：
     * 如果是请求首页的数据，那么就不传 bangumiId 和 userZone，sort 为 active
     * 如果是请求番剧页的数据，那么就传 bangumiId，sort 为 active
     * 如果是请求用户页的数据，那么就传 userZone，sort 为 news
     *
     * > 支持的 type：
     * post, image, score, role, question, answer
     *
     * > 支持的 sort：
     * news，active，hot
     *
     * @Get("/flow/list")
     *
     * @Parameters({
     *      @Parameter("type", description="哪种类型的数据", type="string", required=true),
     *      @Parameter("sort", description="排序方法", type="string", required=true),
     *      @Parameter("bangumiId", description="番剧的id", type="integer"),
     *      @Parameter("userZone", description="用户的空间名", type="string")
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"}),
     *      @Response(204)
     * })
     */
    public function flowlist(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'userZone' => 'string',
                'bangumiId' => 'integer',
                'type' => [
                    'required',
                    Rule::in(['post', 'image', 'score', 'role', 'question', 'answer']),
                ],
                'sort' => [
                    'required',
                    Rule::in(['news', 'active', 'hot']),
                ],
            ]
        );

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();
        $bangumiId = $request->get('bangumiId') ?: 0;
        $userZone = $request->get('userZone') ?: '';
        $type = $request->get('type');

        if ($bangumiId && $userZone)
        {
            return $this->resErrBad();
        }

        if ($bangumiId || $userZone)
        {
            $repository = $type === 'answer'
                ? new QuestionRepository()
                : new BangumiRepository();

            if ($bangumiId)
            {
                $parent = $repository->item($bangumiId);
                if (is_null($parent))
                {
                    return $this->resErrNotFound();
                }
            }
            if ($userZone)
            {
                $userId = $repository->getUserIdByZone($userZone);
                if (!$userId)
                {
                    return $this->resErrNotFound();
                }
            }
        }

        $trendingService = $this->getTrendingServiceByType($type, $bangumiId, $userId);

        $take = $request->get('take') ?: 10;
        $sort = $request->get('sort');
        if ($userZone)
        {
            $page = $request->get('page') ?: 0;

            return $this->resOK($trendingService->users($page, $take));
        }
        if ($sort === 'news')
        {
            $minId = intval($request->get('minId')) ?: 0;

            return $this->resOK($trendingService->news($minId, $take));
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];

//        if ($sort === 'active')
//        {
//            return $this->resOK($trendingService->active($seen, $take));
//        }

        return $this->resOK($trendingService->hot($seen, $take));
    }

    public function mixinFlow(Request $request)
    {
        $take = $request->get('take') ?: 10;
        $seen = $request->get('seen_ids') ? explode(',', $request->get('seen_ids')) : [];
        $bangumiId = $request->get('bangumi_id') ?: 0;
        $postRepository = new PostRepository();
        $idsObj = $postRepository->mixinFlowIds($seen, $take, $bangumiId);
        if (empty($idsObj['ids']))
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true,
                'total' => $idsObj['total']
            ]);
        }

        $imageRepository = new ImageRepository();
        $scoreRepository = new ScoreRepository();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();
        $userTransformer = new UserTransformer();
        $bangumiTransformer = new BangumiTransformer();
        $postTransformer = new PostTransformer();
        $imageTransformer = new ImageTransformer();
        $scoreTransformer = new ScoreTransformer();

        $scoreLikeService = new ScoreLikeService();
        $scoreMarkService = new ScoreMarkService();
        $scoreCommentService = new ScoreCommentService();

        $imageLikeService = new ImageLikeService();
        $imageMarkService = new ImageMarkService();
        $imageCommentService = new ImageCommentService();

        $postLikeService = new PostLikeService();
        $postMarkService = new PostMarkService();
        $postCommentService = new PostCommentService();

        $result = [];
        foreach ($idsObj['ids'] as $item)
        {
            $arr = explode('-', $item);
            $type = $arr[0];
            $id = $arr[1];
            $object = null;
            $repository = null;
            $likeService = null;
            $commentService = null;
            $markService = null;
            if ($type === 'post')
            {
                $object = $postRepository->item($id);
                $transformer = $postTransformer;
                $likeService = $postLikeService;
                $markService = $postMarkService;
                $commentService = $postCommentService;
            }
            else if ($type === 'image')
            {
                $object = $imageRepository->item($id);
                $transformer = $imageTransformer;
                $likeService = $imageLikeService;
                $markService = $imageMarkService;
                $commentService = $imageCommentService;
            }
            else if ($type === 'score')
            {
                $object = $scoreRepository->item($id);
                $transformer = $scoreTransformer;
                $likeService = $scoreLikeService;
                $markService = $scoreMarkService;
                $commentService = $scoreCommentService;
            }
            if (is_null($object))
            {
                continue;
            }

            $user = $userRepository->item($object['user_id']);
            if (is_null($user))
            {
                continue;
            }

            $bangumi = $bangumiId ? null : $bangumiRepository->item($object['bangumi_id']);
            if (is_null($bangumi))
            {
                continue;
            }

            $object['like_count'] = $likeService->total($id);
            $object['mark_count'] = $markService->total($id);
            $object['comment_count'] = $commentService->getCommentCount($id);
            $object['reward_count'] = 0;

            $result[] = [
                'type' => $type,
                'object' => [
                    'user' => $userTransformer->item($user),
                    'bangumi' => $bangumiTransformer->item($bangumi),
                    'flow' => $transformer->baseFlow($object)
                ]
            ];
        }

        return $this->resOK([
            'list' => $result,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

    // 获取信息流的统计数据，App暂无需求，之后可用于下拉刷新提示
    public function meta(Request $request)
    {
        $type = $request->get('type');
        if ($type === 'post')
        {
            $collectionCount = new TotalBangumiCount();
            $totalCount = new TotalPostCount();
        }
        else if ($type === 'image')
        {
            $collectionCount = new TotalImageAlbumCount();
            $totalCount = new TotalImageCount();
        }
        else if ($type === 'score')
        {
            $collectionCount = new TotalBangumiCount();
            $totalCount = new TotalScoreCount();
        }
        else if ($type === 'question')
        {
            $collectionCount = new TotalQuestionCount();
            $totalCount = new TotalAnswerCount();
        }
        else
        {
            return $this->resErrBad();
        }

        return $this->resOK([
            'collection' => $collectionCount->get(),
            'total' => $totalCount->get()
        ]);
    }

    // 推荐板块
    public function recommended(Request $request)
    {
        $repository = new Repository();
        $isAdmin = $request->get('is_admin') ?: '0';
        $result = $repository->Cache("index-recommended-bangumis-{$isAdmin}", function () use ($isAdmin)
        {
            $indexTag = new IndexTagService();
            $bangumiRepository = new BangumiRepository();
            $allTag = $indexTag->all();
            $result = [];
            foreach ($allTag as $tag)
            {
                $item['tag'] = $tag;
                $bangumis = [];
                $bangumiIds = $indexTag->tagModals($tag->id);
                foreach ($bangumiIds as $bid)
                {
                    $bangumi = $bangumiRepository->item($bid);
                    if (is_null($bangumi))
                    {
                        continue;
                    }
                    $bangumis[] = [
                        'id' => $bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                }
                $item['bangumis'] = $bangumis;

                $result[] = $item;
            }

            if ($isAdmin === '0')
            {
                return array_filter($result, function ($item)
                {
                    return count($item['bangumis']);
                });
            }

            return $result;
        });

        return $this->resOK($result);
    }

    protected function getTrendingServiceByType($type, $bangumiId = 0, $userId = 0)
    {
        if ($type === 'post')
        {
            return new PostTrendingService($bangumiId, $userId);
        }
        else if ($type === 'image')
        {
            return new ImageTrendingService($bangumiId, $userId);
        }
        else if ($type === 'score')
        {
            return new ScoreTrendingService($bangumiId, $userId);
        }
        else if ($type === 'role')
        {
            return new CartoonRoleTrendingService($bangumiId, $userId);
        }
        else if ($type === 'question')
        {
            return new QuestionTrendingService($bangumiId, $userId);
        }
        else if ($type === 'answer')
        {
            return new AnswerTrendingService($bangumiId, $userId);
        }

        return null;
    }
}
