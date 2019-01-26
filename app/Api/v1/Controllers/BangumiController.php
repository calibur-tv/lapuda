<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiSeasonRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Activity\BangumiActivity;
use App\Api\V1\Services\Activity\UserActivity;
use App\Api\V1\Services\Counter\BangumiScoreCounter;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Tag\BangumiTagService;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Bangumi\BangumiScoreService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Models\Bangumi;
use App\Api\V1\Repositories\BangumiRepository;
use App\Models\BangumiSeason;
use App\Models\Video;
use App\Services\OpenSearch\Search;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("番剧相关接口")
 */
class BangumiController extends Controller
{
    /**
     * 番剧时间轴
     *
     * @Get("/bangumi/timeline")
     *
     * @Parameters({
     *      @Parameter("year", description="从哪一年开始获取", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"list": "番剧列表", "noMore": "没有更多了"}}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"})
     * })
     */
    public function timeline(Request $request)
    {
        $year = intval($request->get('year'));
        $take = 1;

        if (!$year)
        {
            return $this->resErrBad();
        }

        $bangumiRepository = new BangumiRepository();
        $list = [];
        $minYear = intval($bangumiRepository->timelineMinYear());

        for ($i = 0; $i < $take; $i++)
        {
            $list = array_merge($list, $bangumiRepository->timeline($year - $i));
        }

        return $this->resOK([
            'list' => $list,
            'noMore' => $year <= $minYear
        ]);
    }

    /**
     * 新番列表（周）
     *
     * @Get("/bangumi/released")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧列表"})
     * })
     */
    public function released()
    {
        $data = Cache::remember('bangumi_release_list', 60, function ()
        {
            $ids = Bangumi::where('released_at', '<>', 0)
                ->orderBy('released_time', 'DESC')
                ->pluck('id');

            $repository = new BangumiRepository();
            $list = $repository->list($ids);

            $result = [
                [], [], [], [], [], [], [], []
            ];
            foreach ($list as $item)
            {
                $item['update'] = time() - $item['released_time'] < 604800;
                $id = $item['released_at'];
                $result[$id][] = $item;
                $result[0][] = $item;
            }

            $bangumiTransformer = new BangumiTransformer();
            foreach ($result as $i => $arr)
            {
                $result[$i] = $bangumiTransformer->released($arr);
            }

            return $result;
        });

        return $this->resOK($data);
    }

    /**
     * 所有的番剧标签
     *
     * @Get("/bangumi/tags")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "标签列表"})
     * })
     */
    public function tags()
    {
        $bangumiTagService = new BangumiTagService();

        return $this->resOK($bangumiTagService->all());
    }

    /**
     * 根据标签获取番剧列表
     *
     * @Get("/bangumi/category")
     *
     * @Parameters({
     *      @Parameter("id", description="选中的标签id，`用 - 链接的字符串`", type="string", required=true),
     *      @Parameter("page", description="页码", type="integer", default=0, required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"list": "番剧列表", "total": "该标签下番剧的总数", "noMore": "是否没有更多了"}}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"})
     * })
     */
    public function category(Request $request)
    {
        $tags = $request->get('id');
        $page = $request->get('page') ?: 0;

        if (is_null($tags))
        {
            return $this->resErrBad();
        }

        // 格式化为数组 -> 只保留数字 -> 去重 -> 保留value
        $tags = array_values(array_unique(array_filter(explode('-', $tags), function ($tag) {
            return !preg_match("/[^\d-., ]/", $tag);
        })));

        if (empty($tags))
        {
            return $this->resErrBad();
        }

        sort($tags);
        $repository = new BangumiRepository();

        return $this->resOK($repository->category($tags, $page));
    }

    /**
     * 番剧详情
     *
     * @Get("/bangumi/`bangumiId`/show")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧信息"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的番剧"})
     * })
     */
    public function show(Request $request, $id)
    {
        $repository = new BangumiRepository();
        $bangumi = $repository->item($id);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound('没有找到番剧');
        }

        $userId = $this->getAuthUserId();

        $bangumiFollowService = new BangumiFollowService();
        $bangumi['follow_users'] = $bangumiFollowService->users($id);
        $bangumi['followed'] = $bangumiFollowService->check($userId, $id);

        $bangumiScoreService = new BangumiScoreService();
        $bangumiScoreCounter = new BangumiScoreCounter();
        $bangumi['count_score'] = $bangumiScoreService->total($id);
        $bangumi['scored'] = $bangumiScoreService->check($userId, $id);
        $bangumi['score'] = $bangumiScoreCounter->get($id);

        $bangumiManager = new BangumiManager();
        $bangumi['is_master'] = $bangumiManager->isOwner($id, $userId);
        $bangumi['manager_users'] = $bangumiManager->users($id);

        $bangumiTagService = new BangumiTagService();
        $bangumi['tags'] = $bangumiTagService->tags($id);

        $bangumiActivityService = new BangumiActivity();
        $bangumi['power'] = $bangumiActivityService->get($id);

        $bangumiTransformer = new BangumiTransformer();

        $searchService = new Search();
        if ($searchService->checkNeedMigrate('bangumi', $id))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('bangumi', $id));
            dispatch($job);
        }
        $shareData = [
            'title' => $bangumi['name'],
            'link' => "https://m.calibur.tv/bangumi/{$id}",
            'desc' => $bangumi['summary'],
            'image' => "{$bangumi['avatar']}-share120jpg"
        ];
        $bangumi['share_data'] = $shareData;
        $iOS = $request->get('from') == 'ios';
        if ($iOS)
        {
            $bangumi['has_video'] = false;
            $bangumi['has_cartoon'] = false;
        }

        return $this->resOK($bangumiTransformer->show($bangumi));
    }

    /**
     * 番剧视频
     *
     * @Get("/bangumi/`bangumiId`/videos")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"videos": "视频列表", "has_season": "是否有多个季度", "total": "视频总数"}}),
     *      @Response(404, body={"code": 40401, "message": "不存在的番剧"})
     * })
     */
    public function videos($id)
    {
        $repository = new BangumiRepository();
        $bangumi = $repository->item($id);

        if (is_null($bangumi))
        {
            return $this->resErrNotFound('没有找到番剧');
        }

        return $this->resOK($repository->videos($id, json_decode($bangumi['season'])));
    }

    /**
     * 推荐番剧列表
     *
     * @Get("/bangumi/recommended")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧列表"}),
     * })
     */
    public function recommendedBangumis()
    {
        $ids = [
            [
                'id' => 1,
                'tag' => '虚拟的世界'
            ],
            [
                'id' => 7,
                'tag' => '泪腺崩坏'
            ],
            [
                'id' => 2,
                'tag' => '有生之年'
            ],
            [
                'id' => 24,
                'tag' => '真相就是没有真相'
            ],
            [
                'id' => 72,
                'tag' => '强者世界'
            ],
            [
                'id' => 37,
                'tag' => '治愈X致郁'
            ],
            [
                'id' => 6,
                'tag' => '真正的英雄'
            ],
            [
                'id' => 9,
                'tag' => '不老不死'
            ],
            [
                'id' => 33,
                'tag' => '神话的战斗'
            ],
            [
                'id' => 8,
                'tag' => '毁灭世界'
            ],
            [
                'id' => 160,
                'tag' => 'BK-201'
            ],
            [
                'id' => 338,
                'tag' => '德国骨科'
            ],
            [
                'id' => 84,
                'tag' => '欧拉欧拉'
            ],
            [
                'id' => 46,
                'tag' => '人与非人'
            ],
            [
                'id' => 22,
                'tag' => '唯一的神'
            ],
            [
                'id' => 10,
                'tag' => '白夜叉降诞'
            ],
            [
                'id' => 30,
                'tag' => '圆环之理'
            ],
            [
                'id' => 29,
                'tag' => '剑'
            ],
            [
                'id' => 1,
                'tag' => '游戏世界'
            ],
            [
                'id' => 20,
                'tag' => '直男军宅'
            ]
        ];

        $bangumiRepository = new BangumiRepository();
        $result = [];
        foreach ($ids as $item)
        {
            $bangumi = $bangumiRepository->item($item['id']);
            if (is_null($bangumi))
            {
                continue;
            }
            $bangumi['tag'] = $item['tag'];
            $result[] = $bangumi;
        }

        $bangumiTransformer = new BangumiTransformer();

        return $this->resOK($bangumiTransformer->recommended($result));
    }

    /**
     * 热门番剧列表
     *
     * @Get("/bangumi/hots")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧列表"}),
     * })
     */
    public function hotBangumis()
    {
        $bangumiActivityService = new BangumiActivity();
        $ids = $bangumiActivityService->recentIds();
        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $bangumiRepository = new BangumiRepository();
        $list = $bangumiRepository->list(array_slice($ids, 0, 20));
        $bangumiTransformer = new BangumiTransformer();

        return $this->resOK($bangumiTransformer->list($list));
    }

    /**
     * 吧主编辑番剧信息
     *
     * @Post("/bangumi/`bangumiId`/edit")
     *
     * @Parameters({
     *      @Parameter("avatar", description="封面图链接，不包含 host", type="string", required=true),
     *      @Parameter("banner", description="背景图链接，不包含 host", type="string", required=true),
     *      @Parameter("summary", description="200字以内的纯文本", type="string", required=true),
     *      @Parameter("tags", description="标签的id数组", type="array", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误或内容非法"}),
     *      @Response(403, body={"code": 40301, "message": "权限不足"}),
     *      @Response(503, body={"50301": 40301, "message": "服务暂不可用"})
     * })
     */
    public function editBangumiInfo(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'banner' => 'required|string',
            'avatar' => 'required|string',
            'summary' => 'required|max:200',
            'tags' => 'array'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($id);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        $bangumiManager = new BangumiManager();
        if (!$bangumiManager->isOwner($id, $userId))
        {
            return $this->resErrRole();
        }

        $summary = Purifier::clean($request->get('summary'));
        $avatar = $bangumiRepository->convertImagePath($request->get('avatar'));
        $banner = $bangumiRepository->convertImagePath($request->get('banner'));

        $wordsFilter = new WordsFilter();
        if ($wordsFilter->count($summary) > 2)
        {
            return $this->resErrBad('修改文本不合法，请联系管理员查看');
        }

        $imageFilter = new ImageFilter();
        if ($imageFilter->bad($avatar) || $imageFilter->bad($banner))
        {
            return $this->resErrBad('修改图片不合法，请联系管理员查看');
        }

        DB::beginTransaction();
        $rollback = false;
        $bangumiTagService = new BangumiTagService();
        $result = $bangumiTagService->update($id, $request->get('tags'));
        if (!$result)
        {
            $rollback = true;
        }

        $result = Bangumi::where('id', $id)
            ->update([
                'summary' => $summary,
                'avatar' => $avatar,
                'banner' => $banner,
                'state' => $userId
            ]);
        if ($result === false)
        {
            $rollback = true;
        }

        if ($rollback)
        {
            DB::rollBack();
            return $this->resErrServiceUnavailable('更新失败');
        }
        else
        {
            DB::commit();

            Redis::DEL('bangumi_' . $id);
            Redis::DEL('bangumi_'. $id .'_tags');

            $bangumiRepository = new BangumiRepository();
            $bangumiRepository->migrateSearchIndex('U', $id);

            return $this->resOK();
        }
    }

    // 后台给番剧上新（视频）
    public function updateBangumiRelease(Request $request)
    {
        $season_id = $request->get('season_id');
        $video_id = $request->get('video_id');

        $video = Video::find($video_id);
        if (is_null($video))
        {
            return $this->resErrBad('不存在的视频');
        }
        if ($video['bangumi_season_id'] != $season_id)
        {
            return $this->resErrBad('该视频不属于该番剧');
        }

        BangumiSeason
            ::where('id', $season_id)
            ->update([
                'released_time' => time()
            ]);

        Redis::DEL('bangumi_release_list');
        Redis::DEL('video_' . $video_id);

        $job = (new \App\Jobs\Push\Baidu('bangumi/news'));
        dispatch($job);

        return $this->resNoContent();
    }

    // 后台获取番剧列表
    public function adminList(Request $request)
    {
        $curPage = $request->get('cur_page') ?: 0;
        $toPage = $request->get('to_page') ?: 1;
        $take = $request->get('take') ?: 10;

        $list = Bangumi::withTrashed()
            ->orderBy('id', 'DESC')
            ->select('id', 'name', 'deleted_at')
            ->take(($toPage - $curPage) * $take)
            ->skip($curPage * $take)
            ->get();

        return $this->resOK([
            'list' => $list,
            'total' => Bangumi::count()
        ]);
    }

    // 后台软删除番剧
    public function deleteBangumi(Request $request)
    {
        $id = $request->get('id');
        $bangumi = Bangumi::withTrashed()->find($id);

        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        if (is_null($bangumi->deleted_at))
        {
            $bangumi->delete();

            $job = (new \App\Jobs\Search\Index('D', 'bangumi', $id));
            dispatch($job);

            Redis::DEL('bangumi_'.$id);
        }
        else
        {
            $bangumi->restore();
        }

        return $this->resNoContent();
    }

    // 后台获取番剧详情
    public function getAdminBangumiInfo(Request $request)
    {
        $id = $request->get('id');

        $bangumi = Bangumi::withTrashed()->find($id);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $bangumiTagService = new BangumiTagService();
        $bangumiSeasonRepository = new BangumiSeasonRepository();

        $bangumi['alias'] = $bangumi['alias'] === 'null' ? '' : json_decode($bangumi['alias'])->search;
        $bangumi['tags'] = $bangumiTagService->tags($id);
        $bangumi['season'] = $bangumiSeasonRepository->listByBangumiId($id);

        return $this->resOK($bangumi);
    }

    // 后台创建番剧
    public function create(Request $request)
    {
        $time = Carbon::now();
        $bangumiId = Bangumi::insertGetId([
            'name' => $request->get('name'),
            'avatar' => $request->get('avatar'),
            'banner' => $request->get('banner'),
            'summary' => $request->get('summary'),
            'published_at' => $request->get('published_at'),
            'season' => 'null',
            'alias' => $request->get('alias') ? json_encode([
                'search' => $request->get('alias')
            ]) : 'null',
            'created_at' => $time,
            'updated_at' => $time
        ]);

        BangumiSeason::create([
            'bangumi_id' => $bangumiId,
            'name' => '',
            'rank' => 1,
            'summary' => $request->get('summary'),
            'avatar' => $request->get('avatar'),
            'published_at' => Carbon::createFromTimestamp($request->get('published_at'))->toDateTimeString(),
            'other_site_video' => $request->get('others_site_video'),
            'released_at' => $request->get('released_at'),
            'end' => $request->get('end')
        ]);

        $bangumiTagService = new BangumiTagService();
        $bangumiTagService->update($bangumiId, $request->get('tags'));

        Redis::DEL('bangumi_all_list');

        $bangumiRepository = new BangumiRepository();
        $bangumiRepository->migrateSearchIndex('C', $bangumiId);

        return $this->resCreated($bangumiId);
    }

    // 后台编辑番剧
    public function edit(Request $request)
    {
        $rollback = false;
        $bangumiId = $request->get('id');
        DB::beginTransaction();

        $bangumiTagService = new BangumiTagService();
        $result = $bangumiTagService->update($bangumiId, $request->get('tags'));

        if (!$result)
        {
            $rollback = true;
        }

        $bangumi = Bangumi::withTrashed()->where('id', $bangumiId)->first();
        $arr = [
            'name' => $request->get('name'),
            'avatar' => $request->get('avatar'),
            'banner' => $request->get('banner'),
            'summary' => $request->get('summary'),
            'alias' => $request->get('alias') ? json_encode([
                'search' => $request->get('alias')
            ]) : 'null',
            'has_cartoon' => $request->get('has_cartoon'),
            'has_video' => $request->get('has_video')
        ];

        $result = $bangumi->update($arr);
        if ($result === false)
        {
            $rollback = true;
        }

        if ($rollback)
        {
            DB::rollBack();

            return $this->resErrBad('更新失败');
        }
        else
        {
            DB::commit();

            Redis::DEL('bangumi_'.$bangumiId);
            Redis::DEL('bangumi_'.$bangumiId.'_videos');

            $bangumiRepository = new BangumiRepository();
            $bangumiRepository->migrateSearchIndex('U', $bangumiId);

            return $this->resNoContent();
        }
    }

    // 后台设置番剧管理员
    public function setManager(Request $request)
    {
        $userId = $request->get('user_id');
        $bangumiId = $request->get('bangumi_id');

        $bangumiManager = new BangumiManager();
        $result = $bangumiManager->set($bangumiId, $userId);

        if (!$result)
        {
            return $this->resErrBad();
        }

        return $this->resNoContent();
    }

    // 后台撤销番剧管理员
    public function removeManager(Request $request)
    {
        $userId = $request->get('user_id');
        $bangumiId = $request->get('bangumi_id');

        $bangumiManager = new BangumiManager();
        $result = $bangumiManager->remove($bangumiId, $userId);

        if (!$result)
        {
            return $this->resErrBad();
        }

        return $this->resNoContent();
    }

    // 后台升级番剧管理员权限
    public function upgradeManager(Request $request)
    {
        $userId = $request->get('user_id');
        $bangumiId = $request->get('bangumi_id');

        $bangumiManager = new BangumiManager();
        $result = $bangumiManager->upgrade($bangumiId, $userId);

        if (!$result)
        {
            return $this->resErrBad();
        }

        return $this->resNoContent();
    }

    // 后台降级番剧管理员权限
    public function downgradeManager(Request $request)
    {
        $userId = $request->get('user_id');
        $bangumiId = $request->get('bangumi_id');

        $bangumiManager = new BangumiManager();
        $result = $bangumiManager->downgrade($bangumiId, $userId);

        if (!$result)
        {
            return $this->resErrBad();
        }

        return $this->resNoContent();
    }

    // 后台番剧待审列表
    public function trials()
    {
        $bangumiIds = Bangumi::where('state', '<>', 0)
            ->pluck('id');

        if (is_null($bangumiIds))
        {
            return $this->resOK([]);
        }

        $bangumiRepository = new BangumiRepository();

        $list = $bangumiRepository->list($bangumiIds);

        return $this->resOK($list);
    }

    // 后台通过番剧
    public function pass(Request $request)
    {
        Bangumi::where('id', $request->get('id'))
            ->update([
                'state' => 0
            ]);

        return $this->resNoContent();
    }

    public function managers(Request $request)
    {
        $sort = $request->get('sort') ?: 'bangumi';

        $bangumiRepository = new BangumiRepository();
        $userRepository = new UserRepository();
        $bangumiActivityService = new BangumiActivity();
        $userActivityService = new UserActivity();
        $bangumiManager = new BangumiManager();

        $result = [];
        if ($sort === 'bangumi')
        {
            $bangumiIds = DB::table('bangumi_managers')
                ->groupBy('modal_id')
                ->pluck('modal_id');

            foreach ($bangumiIds as $id)
            {
                $item = [];

                $userIds = DB
                    ::table('bangumi_managers')
                    ->where('modal_id', $id)
                    ->pluck('user_id')
                    ->toArray();

                $item['data'] = $bangumiRepository->item($id);
                $users = $userRepository->list($userIds);
                foreach ($users as $i => $user)
                {
                    $users[$i]['power'] = $userActivityService->get($user['id']);
                    $users[$i]['is_leader'] = $bangumiManager->isLeader($id, $user['id']);
                }
                $item['list'] = $users;
                $item['power'] = $bangumiActivityService->get($id);

                $result[] = $item;
            }
        }
        else
        {
            $userIds = DB::table('bangumi_managers')
                ->groupBy('user_id')
                ->pluck('user_id');

            foreach ($userIds as $id)
            {
                $item = [];

                $userIds = DB
                    ::table('bangumi_managers')
                    ->where('user_id', $id)
                    ->pluck('modal_id')
                    ->toArray();

                $item['data'] = $userRepository->item($id);
                $bangumis = $bangumiRepository->list($userIds);
                foreach ($bangumis as $i => $bangumi)
                {
                    $bangumis[$i]['power'] = $bangumiActivityService->get($bangumi['id']);
                    $bangumis[$i]['is_leader'] = $bangumiManager->isLeader($bangumi['id'], $id);
                }
                $item['list'] = $bangumis;
                $item['power'] = $userActivityService->get($id);

                $result[] = $item;
            }
        }

        return $this->resOK($result);
    }
}
