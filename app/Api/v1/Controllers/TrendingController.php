<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Services\Counter\Stats\TotalAnswerCount;
use App\Api\V1\Services\Counter\Stats\TotalBangumiCount;
use App\Api\V1\Services\Counter\Stats\TotalImageAlbumCount;
use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Api\V1\Services\Counter\Stats\TotalPostCount;
use App\Api\V1\Services\Counter\Stats\TotalQuestionCount;
use App\Api\V1\Services\Counter\Stats\TotalScoreCount;
use App\Api\V1\Services\Trending\AnswerTrendingService;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Api\V1\Services\Trending\QuestionTrendingService;
use App\Api\V1\Services\Trending\CartoonRoleTrendingService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
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
                    Rule::in(['post', 'image', 'score', 'role', 'question','answer']),
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

        if ($sort === 'active')
        {
            return $this->resOK($trendingService->active($seen, $take));
        }

        return $this->resOK($trendingService->hot($seen, $take));
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
