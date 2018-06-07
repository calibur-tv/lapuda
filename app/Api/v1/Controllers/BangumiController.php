<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Counter\Post\PostReplyCounter;
use App\Api\V1\Services\Counter\Post\PostViewCounter;
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
use App\Api\V1\Repositories\TagRepository;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @Resource("番剧相关接口")
 */
class BangumiController extends Controller
{
    /**
     * 获取番剧时间轴
     *
     * @Get("/bangumi/timeline")
     *
     * @Parameters({
     *      @Parameter("year", description="从哪一年开始获取", required=true),
     *      @Parameter("take", description="一次获取几年的内容", default=3)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"list": "番剧列表", "min": "可获取到数据的最小年份"}}),
     *      @Response(400, body={"code": 40003, "message": "没有传年份", "data": ""})
     * })
     */
    public function timeline(Request $request)
    {
        $year = intval($request->get('year'));
        $take = intval($request->get('take')) ?: 3;
        if (!$year)
        {
            return $this->resErrParams();
        }

        $repository = new BangumiRepository();
        $list = [];

        for ($i = 0; $i < $take; $i++) {
            $list = array_merge($list, $repository->timeline($year - $i));
        }

        return $this->resOK([
            'list' => $list,
            'min' => intval($repository->timelineMinYear())
        ]);
    }

    /**
     * 获取新番列表
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
     * 获取所有的番剧标签
     *
     * @Get("/bangumi/tags")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "标签列表"})
     * })
     */
    public function tags()
    {
        $tagRepository = new TagRepository();

        return $this->resOK($tagRepository->all(0));
    }

    /**
     * 根据参数获取番剧列表
     *
     * @Get("/bangumi/category")
     *
     * @Parameters({
     *      @Parameter("id", description="选中的标签id，用 - 链接的字符串", required=true),
     *      @Parameter("page", description="页码", required=true, default=1)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧列表"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误", "data": ""})
     * })
     */
    public function category(Request $request)
    {
        $tags = $request->get('id');
        $page = $request->get('page') ?: 1;

        if (is_null($tags))
        {
            return $this->resErrParams();
        }

        // 格式化为数组 -> 只保留数字 -> 去重 -> 保留value
        $tags = array_values(array_unique(array_filter(explode('-', $tags), function ($tag) {
            return !preg_match("/[^\d-., ]/", $tag);
        })));

        if (empty($tags))
        {
            return $this->resErrParams();
        }

        sort($tags);
        $repository = new BangumiRepository();

        return $this->resOK($repository->category($tags, $page));
    }

    /**
     * 获取番剧详情
     *
     * @Get("/bangumi/${bangumiId}/show")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧对象"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的番剧", "data": ""})
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

        $transformer = new BangumiTransformer();

        return $this->resOK($transformer->show($bangumi));
    }

    /**
     * 获取番剧视频
     *
     * @Get("/bangumi/${bangumiId}/videos")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"videos": "视频列表", "repeat": "视频集数是否连续", "total": "视频总数"}}),
     *      @Response(404, body={"code": 40401, "message": "不存在的番剧", "data": ""})
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
     * @Post("/bangumi/${bangumiId}/follow")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     *
     * @Transaction({
     *      @Response(201, body={"code": 0, "data": "是否已关注"}),
     *      @Response(401, body={"code": 40104, "message": "用户认证失败", "data": ""})
     * })
     */
    public function follow($id)
    {
        $bangumiFollowService = new BangumiFollowService();
        $result = $bangumiFollowService->toggle($this->getAuthUserId(), $id);

        return $this->resCreated((boolean)$result);
    }

    /**
     * 获取关注番剧的用户列表
     *
     * @Post("/bangumi/${bangumiId}/followers")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的userIds，用','隔开的字符串", "take": "获取数量"}),
     *      @Response(200, body={"code": 0, "data": "用户列表"})
     * })
     */
    public function followers(Request $request, $bangumiId)
    {
        $take = intval($request->get('take')) ?: 10;
        $page = intval($request->get('page')) ?: 0;

        $bangumiFollowService = new BangumiFollowService();
        $users = $bangumiFollowService->users($bangumiId, $page, $take);

        return $this->resOK($users);
    }

    /**
     * 番剧的帖子列表
     *
     * @Post("/bangumi/${bangumiId}/posts")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的userIds，用','隔开的字符串", "take": "获取数量"}, headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": {"list": "番剧列表", "total": "总数"}})
     * })
     */
    public function posts(Request $request, $id)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;
        $type = $request->get('type') ?: 'new';

        $bangumiRepository = new BangumiRepository();
        $ids = $bangumiRepository->getPostIds($id, $type);

        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'total' => 0
            ]);
        }

        $userId = $this->getAuthUserId();
        $postRepository = new PostRepository();
        $list = $postRepository->list(array_slice(array_diff($ids, $seen), 0, $take));

        $postCommentService = new PostCommentService();
        $postLikeService = new PostLikeService();
        $postMarkService = new PostMarkService();
        $postViewCounter = new PostViewCounter();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();
        $postReplyCounter = new PostReplyCounter();

        foreach ($list as $i => $item)
        {
            $id = $item['id'];
            $authorId = $item['user_id'];
            $list[$i]['liked'] = $postLikeService->check($userId, $id, $authorId);
            $list[$i]['like_count'] = $postLikeService->total($id);
            $list[$i]['marked'] = $postMarkService->check($userId, $id, $authorId);
            $list[$i]['mark_count'] = $postMarkService->total($id);
            $list[$i]['commented'] = $postCommentService->check($userId, $id);
            $list[$i]['comment_count'] = $postReplyCounter->get($id);
            $list[$i]['view_count'] = $postViewCounter->get($id);
            $list[$i]['user'] = $userRepository->item($authorId);
            $list[$i]['bangumi'] = $bangumiRepository->item($item['bangumi_id']);
        }
        $transformer = new PostTransformer();

        return $this->resOK([
            'list' => $transformer->bangumi($list),
            'total' => count($ids)
        ]);
    }

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
            ->whereIn('state', [1, 4])
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

        $allIds = explode(',', $idsStr);
        $ids = array_slice($allIds, $page * $take, $take);

        $transformer = new ImageTransformer();

        $visitorId = $this->getAuthUserId();
        $list = $imageRepository->list($ids);
        $imageLikeService = new ImageLikeService();

        foreach ($list as $i => $item)
        {
            if ($list[$i]['image_count'])
            {
                $list[$i]['liked'] = $imageLikeService->check($visitorId, $item['id'], $item['user_id']);
            }
            else
            {
                unset($list[$i]);
            }
        }

        return $this->resOK([
            'list' => $transformer->cartoon($list),
            'noMore' => count($allIds) - ($take * ($page + 1)) <= 0
        ]);
    }
}
