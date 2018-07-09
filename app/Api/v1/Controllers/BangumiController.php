<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Counter\PostViewCounter;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Tag\BangumiTagService;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\ImageTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Models\Bangumi;
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Models\Image;
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

        $repository = new BangumiRepository();
        $list = [];
        $minYear = intval($repository->timelineMinYear());

        for ($i = 0; $i < $take; $i++) {
            $list = array_merge($list, $repository->timeline($year - $i));
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

            $transformer = new BangumiTransformer();
            foreach ($result as $i => $arr)
            {
                $result[$i] = $transformer->released($arr);
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
    public function show($id)
    {
        $repository = new BangumiRepository();
        $bangumi = $repository->item($id);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound('没有找到番剧');
        }

        $userId = $this->getAuthUserId();
        $bangumiFollowService = new BangumiFollowService();

        $bangumi['count_like'] = $bangumiFollowService->total($id);
        $bangumi['followers'] = $bangumiFollowService->users($id);
        $bangumi['followed'] = $bangumiFollowService->check($userId, $id);

        $bangumiManager = new BangumiManager();
        $bangumi['is_master'] = $bangumiManager->isOwner($id, $userId);
        $bangumi['managers'] = $bangumiManager->getOwners($id);

        $bangumiTagService = new BangumiTagService();
        $bangumi['tags'] = $bangumiTagService->tags($id);

        $transformer = new BangumiTransformer();

        return $this->resOK($transformer->show($bangumi));
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
     * 关注或取消关注番剧
     *
     * @Post("/bangumi/`bangumiId`/toggleFollow")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     *
     * @Transaction({
     *      @Response(201, body={"code": 0, "data": "是否已关注"}),
     *      @Response(401, body={"code": 40104, "message": "用户认证失败"})
     * })
     */
    public function toggleFollow($id)
    {
        $userId = $this->getAuthUserId();
        $bangumiManager = new BangumiManager();
        $bangumiFollowService = new BangumiFollowService();
        if ($bangumiManager->isOwner($id, $userId) && $bangumiFollowService->check($userId, $id))
        {
            return $this->resErrRole('管理员不能取消关注');
        }

        $result = $bangumiFollowService->toggle($this->getAuthUserId(), $id);

        return $this->resCreated((boolean)$result);
    }

    /**
     * 关注番剧的用户列表
     *
     * @Get("/bangumi/`bangumiId`/followers")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的userIds，用','隔开的字符串", "take": "获取数量"}),
     *      @Response(200, body={"code": 0, "data": {"list": "用户列表", "noMore": "没有更多"}})
     * })
     */
    public function followers(Request $request, $bangumiId)
    {
        $take = intval($request->get('take')) ?: 10;
        $page = intval($request->get('page')) ?: 0;

        $bangumiFollowService = new BangumiFollowService();
        $users = $bangumiFollowService->users($bangumiId, $page, $take);

        return $this->resOK([
            'list' => $users,
            'noMore' => $bangumiFollowService->total($bangumiId) - (($page + 1) * $take) <= 0
        ]);
    }

    public function managers($bangumiId)
    {
        $bangumiManager = new BangumiManager();
        $managers = $bangumiManager->getOwners($bangumiId);

        return $this->resOK($managers);
    }

    /**
     * 番剧的帖子列表
     *
     * @Get("/bangumi/`bangumiId`/posts/news")
     *
     * @Parameters({
     *      @Parameter("maxId", description="看过的帖子里，最大的一个id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"list": "番剧列表", "total": "总数", "noMore": "没有更多"}})
     * })
     */
    public function newsPosts(Request $request, $id)
    {
        $maxId = intval($request->get('maxId')) ?: 0;
        $take = 10;

        $bangumiRepository = new BangumiRepository();
        $ids = $bangumiRepository->getPostIds($id, 'new');

        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'total' => 0,
                'noMore' => true
            ]);
        }

        $idsObject = $this->filterIdsByMaxId($ids, $maxId, $take);

        $userId = $this->getAuthUserId();
        $postRepository = new PostRepository();
        $list = $postRepository->list($idsObject['ids']);

        $postCommentService = new PostCommentService();
        $postLikeService = new PostLikeService();
        $postMarkService = new PostMarkService();
        $postViewCounter = new PostViewCounter();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();

        foreach ($list as $i => $item)
        {
            $id = $item['id'];

            $authorId = $item['user_id'];
            $list[$i]['view_count'] = $postViewCounter->get($id);

            $list[$i]['user'] = $userRepository->item($authorId);
            $list[$i]['bangumi'] = $bangumiRepository->item($item['bangumi_id']);
        }
        $list = $postLikeService->batchCheck($list, $userId, 'liked');
        $list = $postLikeService->batchTotal($list, 'like_count');
        $list = $postMarkService->batchCheck($list, $userId, 'marked');
        $list = $postMarkService->batchTotal($list, 'mark_count');
        $list = $postCommentService->batchCheckCommented($list, $userId);
        $list = $postCommentService->batchGetCommentCount($list);

        $transformer = new PostTransformer();

        return $this->resOK([
            'list' => $transformer->bangumi($list),
            'total' => $idsObject['total'],
            'noMore' => $idsObject['noMore']
        ]);
    }

    public function topPosts(Request $request, $id)
    {
        $bangumiRepository = new BangumiRepository();
        $ids = $bangumiRepository->getTopPostIds($id);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $userId = $this->getAuthUserId();
        $postRepository = new PostRepository();
        $list = $postRepository->list($ids);

        $postCommentService = new PostCommentService();
        $postLikeService = new PostLikeService();
        $postMarkService = new PostMarkService();
        $postViewCounter = new PostViewCounter();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();

        foreach ($list as $i => $item)
        {
            $id = $item['id'];

            $authorId = $item['user_id'];
            $list[$i]['view_count'] = $postViewCounter->get($id);

            $list[$i]['user'] = $userRepository->item($authorId);
            $list[$i]['bangumi'] = $bangumiRepository->item($item['bangumi_id']);
        }
        $list = $postLikeService->batchCheck($list, $userId, 'liked');
        $list = $postLikeService->batchTotal($list, 'like_count');
        $list = $postMarkService->batchCheck($list, $userId, 'marked');
        $list = $postMarkService->batchTotal($list, 'mark_count');
        $list = $postCommentService->batchCheckCommented($list, $userId);
        $list = $postCommentService->batchGetCommentCount($list);

        $transformer = new PostTransformer();

        return $this->resOK($transformer->bangumi($list));
    }

    // TODO：trending service
    // TODO：api docs
    public function images(Request $request, $id)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 12;
        $size = intval($request->get('size')) ?: 0;
        $creator = intval($request->get('creator'));
        $role = intval($request->get('roleId'));
        $sort = $request->get('sort') ?: 'new';
        $tags = $request->get('tags') ?: 0;

        $imageRepository = new ImageRepository();

        $ids = Image::whereRaw('bangumi_id = ? and album_id = 0 and image_count <> 1 and is_cartoon = 0', [$id])
            ->whereIn('state', [1, 2])
            ->whereNotIn('images.id', $seen)
            ->take($take)
            ->when($sort === 'new', function ($query)
            {
                return $query->latest();
            }, function ($query)
            {
                return $query->orderBy('like_count', 'DESC');
            })
            ->when($role !== -1, function ($query) use ($role)
            {
                return $query->where('role_id', $role);
            })
            ->when($creator !== -1, function ($query) use ($creator)
            {
                return $query->where('creator', $creator);
            })
            ->when($size, function ($query) use ($size)
            {
                return $query->where('size_id', $size);
            })
            ->when($tags, function ($query) use ($tags)
            {
                return $query->leftJoin('image_tags AS tags', 'images.id', '=', 'tags.image_id')
                    ->where('tags.tag_id', $tags);
            })
            ->pluck('images.id');

        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'type' => $imageRepository->uploadImageTypes()
            ]);
        }

        $transformer = new ImageTransformer();

        $visitorId = $this->getAuthUserId();
        $list = $imageRepository->list($ids);

        $imageLikeService = new ImageLikeService();
        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $imageLikeService->check($visitorId, $item['id'], $item['user_id']);
            $list[$i]['like_count'] = $imageLikeService->total($item['id']);
        }

        return $this->resOK([
            'list' => $transformer->waterfall($list),
            'type' => $imageRepository->uploadImageTypes()
        ]);
    }

    /**
     * 番剧的漫画列表
     *
     * @Get("/bangumi/`bangumiId`/cartoon")
     *
     * @Parameters({
     *      @Parameter("page", description="页码", type="integer", default=0, required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"list": "漫画列表", "total": "总数", "noMore": "没有更多"}})
     * })
     */
    public function cartoon(Request $request, $id)
    {
        $take = 12;
        $page = intval($request->get('page')) ?: 0;

        $imageRepository = new ImageRepository();

        $idsStr = Bangumi::where('id', $id)->pluck('cartoon')->first();

        if ($idsStr === '')
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true
            ]);
        }

        $idsObj = $this->filterIdsByPage($idsStr, $page, $take);

        $transformer = new ImageTransformer();

        $visitorId = $this->getAuthUserId();
        $list = $imageRepository->list($idsObj['ids']);
        $imageLikeService = new ImageLikeService();

        foreach ($list as $i => $item)
        {
            if ($list[$i]['image_count'])
            {
                $list[$i]['liked'] = $imageLikeService->check($visitorId, $item['id'], $item['user_id']);
                $list[$i]['like_count'] = $imageLikeService->total($item['id']);
            }
            else
            {
                unset($list[$i]);
            }
        }

        return $this->resOK([
            'list' => $transformer->cartoon($list),
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

    public function updateBangumiRelease(Request $request)
    {
        $bangumi_id = $request->get('bangumi_id');
        $video_id = $request->get('video_id');

        $video = Video::find($video_id);
        if (is_null($video))
        {
            return $this->resErrBad('不存在的视频');
        }

        Bangumi::where('id', $bangumi_id)->update([
            'released_time' => time(),
            'released_video_id' => $video_id
        ]);

        Redis::DEL('bangumi_release_list');
        Redis::DEL('bangumi_' . $bangumi_id);

        $job = (new \App\Jobs\Push\Baidu('bangumi/news'));
        dispatch($job);

        return $this->resNoContent();
    }

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

            $job = (new \App\Jobs\Push\Baidu('bangumi/' . $id, 'del'));
            dispatch($job);

            Redis::DEL('bangumi_'.$id);
        }
        else
        {
            $bangumi->restore();
        }

        return $this->resNoContent();
    }

    public function getAdminBangumiInfo(Request $request)
    {
        $id = $request->get('id');

        $bangumi = Bangumi::withTrashed()->find($id);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $bangumiTagService = new BangumiTagService();

        $bangumi['alias'] = $bangumi['alias'] === 'null' ? '' : json_decode($bangumi['alias'])->search;
        $bangumi['tags'] = $bangumiTagService->tags($id);
        $bangumi['season'] = $bangumi['season'] === 'null' ? '' : $bangumi['season'];
        $bangumi['published_at'] = $bangumi['published_at'] * 1000;

        return $this->resOK($bangumi);
    }

    public function create(Request $request)
    {
        $releasedId = $request->get('released_video_id') ?: 0;
        $time = Carbon::now();
        $bangumi_id = Bangumi::insertGetId([
            'name' => $request->get('name'),
            'avatar' => $request->get('avatar'),
            'banner' => $request->get('banner'),
            'summary' => $request->get('summary'),
            'released_at' => $request->get('released_at'),
            'released_video_id' => $releasedId,
            'season' => $request->get('season') ? $request->get('season') : 'null',
            'alias' => $request->get('alias') ? json_encode([
                'search' => $request->get('alias')
            ]) : 'null',
            'published_at' => $request->get('published_at') ?: 0,
            'others_site_video' => $request->get('others_site_video'),
            'end' => $request->get('end'),
            'created_at' => $time,
            'updated_at' => $time,
            'count_score' => 0
        ]);

        $tags = [];
        foreach($request->get('tags') as $i => $tag_id)
        {
            array_push($tags, [
                'bangumi_id' => $bangumi_id,
                'tag_id' => $tag_id
            ]);
        }
        DB::table('bangumi_tag')->insert($tags);

        if ($releasedId)
        {
            Redis::DEL('bangumi_release_list');
        }
        Redis::DEL('bangumi_all_list');

        $job = (new \App\Jobs\Push\Baidu('bangumi/' . $bangumi_id));
        dispatch($job);

        $searchService = new Search();

        $searchService->create(
            $bangumi_id,
            $request->get('alias'),
            'bangumi'
        );

        return $this->resCreated($bangumi_id);
    }

    public function edit(Request $request)
    {
        $rollback = false;
        $bangumi_id = $request->get('id');
        DB::beginTransaction();

        $bangumiTagService = new BangumiTagService();
        $result = $bangumiTagService->update($bangumi_id, $request->get('tags'));

        if (!$result)
        {
            $rollback = true;
        }

        $bangumi = Bangumi::withTrashed()->where('id', $bangumi_id)->first();
        $arr = [
            'name' => $request->get('name'),
            'avatar' => $request->get('avatar'),
            'banner' => $request->get('banner'),
            'summary' => $request->get('summary'),
            'released_at' => $request->get('released_at'),
            'released_video_id' => $request->get('released_video_id'),
            'season' => $request->get('season') ? $request->get('season') : 'null',
            'alias' => $request->get('alias') ? json_encode([
                'search' => $request->get('alias')
            ]) : 'null',
            'end' => $request->get('end'),
            'published_at' => $request->get('published_at'),
            'others_site_video' => $request->get('others_site_video'),
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

            $searchService = new Search();

            $searchService->update(
                $bangumi_id,
                $request->get('alias'),
                'bangumi'
            );

            Redis::DEL('bangumi_'.$bangumi_id);
            Redis::DEL('bangumi_'.$bangumi_id.'_videos');

            $job = (new \App\Jobs\Push\Baidu('bangumi/' . $bangumi_id, 'update'));
            dispatch($job);

            return $this->resNoContent();
        }
    }

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

        $userId = $this->getAuthUserId();
        $bangumiManager = new BangumiManager();
        if (!$bangumiManager->isOwner($id, $userId))
        {
            return $this->resErrRole();
        }

        $summary = Purifier::clean($request->get('summary'));
        $avatar = $request->get('avatar');
        $banner = $request->get('banner');

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
            return $this->resNoContent();
        }
    }

    public function trialList()
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

    public function trialPass(Request $request)
    {
        Bangumi::where('id', $request->get('id'))
            ->update([
                'state' => 0
            ]);

        return $this->resNoContent();
    }
}
