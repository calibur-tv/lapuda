<?php
/**
 * Created by PhpStorm.
 * User: 23002
 * Date: 2017/11/25
 * Time: 0:20
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Services\Counter\Stats\TotalUserCount;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\ImageTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Feedback;
use App\Models\Image;
use App\Models\Notifications;
use App\Models\User;
use App\Api\V1\Repositories\UserRepository;
use App\Models\UserCoin;
use App\Models\UserSign;
use App\Services\OpenSearch\Search;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("用户相关接口")
 */
class UserController extends Controller
{
    /**
     * 用户每日签到
     *
     * @Post("/user/daySign")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户"}),
     *      @Response(403, body={"code": 40301, "message": "今日已签到"})
     * })
     */
    public function daySign()
    {
        $repository = new UserRepository();
        $userId = $this->getAuthUserId();

        if ($repository->daySigned($userId))
        {
            return $this->resErrRole('已签到');
        }

        UserCoin::create([
            'from_user_id' => $userId,
            'user_id' => $userId,
            'type' => 0
        ]);

        UserSign::create([
            'user_id' => $userId
        ]);

        User::where('id', $userId)->increment('coin_count', 1);

        return $this->resNoContent();
    }

    /**
     * 更新用户资料中的图片
     *
     * @Post("/user/setting/image")
     *
     * @Parameters({
     *      @Parameter("type", description="`avatar`或`banner`", type="string", required=true),
     *      @Parameter("url", description="图片地址，不带`host`", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204)
     * })
     */
    public function image(Request $request)
    {
        $userId = $this->getAuthUserId();
        $key = $request->get('type');

        if (!in_array($key, ['avatar', 'banner']))
        {
            return $this->resErrBad();
        }

        $val = $request->get('url');

        User::where('id', $userId)->update([
            $key => $val
        ]);

        $cache = 'user_'.$userId;
        if (Redis::EXISTS($cache))
        {
            Redis::HSET($cache, $key, config('website.image') . $val);
        }
        $job = (new \App\Jobs\Trial\User\Image($userId, $key));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('user/' . User::where('id', $userId)->pluck('zone')->first(), 'update'));
        dispatch($job);

        return $this->resNoContent();
    }

    /**
     * 用户详情
     *
     * @Get("/user/`zone`/show")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "用户信息对象"}),
     *      @Response(404, body={"code": 40401, "message": "该用户不存在"})
     * })
     */
    public function show($zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('该用户不存在');
        }

        $repository = new UserRepository();
        $transformer = new UserTransformer();
        $user = $repository->item($userId);

        return $this->resOK($transformer->show($user));
    }

    // TODO：用户性别怎么设计
    // TODO：API Doc
    public function profile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sex' => 'required',
            'signature' => 'string|min:0|max:150',
            'nickname' => 'required|min:1|max:14',
            'birthday' => 'required'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();

        User::where('id', $userId)->update([
            'nickname' => Purifier::clean($request->get('nickname')),
            'signature' => Purifier::clean($request->get('signature')),
            'sex' => $request->get('sex'),
            'birthday' => $request->get('birthday')
        ]);

        Redis::DEL('user_'.$userId);
        $job = (new \App\Jobs\Trial\User\Text($userId));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('user/' . User::where('id', $userId)->pluck('zone')->first(), 'update'));
        dispatch($job);

        return $this->resNoContent();
    }

    /**
     * 用户关注的番剧列表
     *
     * @Get("/user/`zone`/followed/bangumi")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧列表"}),
     *      @Response(404, body={"code": 40401, "message": "该用户不存在"})
     * })
     */
    public function followedBangumis($zone)
    {
        $userRepository = new UserRepository();
        $userId = $userRepository->getUserIdByZone($zone);

        if (!$userId)
        {
            return $this->resErrNotFound('该用户不存在');
        }

        $bangumis = $userRepository->followedBangumis($userId);

        return $this->resOK($bangumis);
    }

    /**
     * 用户发布的帖子列表
     *
     * @Get("/user/`zone`/posts/mine")
     *
     * @Transaction({
     *      @Request({"minId": "看过的帖子列表里，id 最小的那个帖子的 id"}),
     *      @Response(200, body={"code": 0, "data": "帖子列表"}),
     *      @Response(404, body={"code": 40401, "message": "找不到用户"})
     * })
     */
    public function postsOfMine(Request $request, $zone)
    {
        $userRepository = new UserRepository();
        $userId = $userRepository->getUserIdByZone($zone);
        if (!$userId)
        {
            return $this->resErrNotFound('找不到用户');
        }

        $ids = $userRepository->minePostIds($userId);
        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'total' => 0,
                'noMore' => true
            ]);
        }

        $minId = $request->get('minId') ?: 0;
        $take = 10;
        $idsObject = $this->filterIdsByMaxId($ids, $minId, $take);

        $postRepository = new PostRepository();
        $postTransformer = new PostTransformer();
        $bangumiRepository = new BangumiRepository();
        $list = $postRepository->list($idsObject['ids']);
        foreach ($list as $i => $item)
        {
            $list[$i]['bangumi'] = $bangumiRepository->item($item['bangumi_id']);
        }

        return $this->resOK([
            'list' => $postTransformer->usersMine($list),
            'total' => $idsObject['total'],
            'noMore' => $idsObject['noMore']
        ]);
    }

    /**
     * 用户回复的帖子列表
     *
     * @Get("/user/`zone`/posts/reply")
     *
     * @Transaction({
     *      @Request({"minId": "看过的帖子列表里，id 最小的那个帖子的 id"}),
     *      @Response(200, body={"code": 0, "data": "帖子列表"}),
     *      @Response(404, body={"code": 40401, "message": "找不到用户"})
     * })
     */
    public function postsOfReply(Request $request, $zone)
    {
        $userRepository = new UserRepository();
        $userId = $userRepository->getUserIdByZone($zone);
        if (!$userId)
        {
            return $this->resErrNotFound('找不到用户');
        }

        $ids = $userRepository->replyPostIds($userId);
        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'total' => 0,
                'noMore' => true
            ]);
        }

        $minId = $request->get('minId') ?: 0;
        $take = 10;
        $idsObject = $this->filterIdsByMaxId($ids, $minId, $take);

        $data = [];
        foreach ($idsObject['ids'] as $id)
        {
            $data[] = $userRepository->replyPostItem($userId, $id);
        }

        $result = [];
        foreach ($data as $item)
        {
            if ($item)
            {
                $result[] = $item;
            }
        }

        return $this->resOK([
            'list' => $result,
            'total' => $idsObject['total'],
            'noMore' => $idsObject['noMore']
        ]);
    }

    /**
     * 用户喜欢的帖子列表
     *
     * @Get("/user/`zone`/posts/like")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "帖子列表"}),
     *      @Response(404, body={"code": 40401, "message": "找不到用户"})
     * })
     */
    public function postsOfLiked(Request $request, $zone)
    {
        $userRepository = new UserRepository();
        $userId = $userRepository->getUserIdByZone($zone);
        if (!$userId)
        {
            return $this->resErrNotFound('找不到用户');
        }

        return $this->resOK($userRepository->likedPost($userId));
    }

    /**
     * 用户收藏的帖子列表
     *
     * @Get("/user/`zone`/posts/mark")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "帖子列表"}),
     *      @Response(404, body={"code": 40401, "message": "找不到用户"})
     * })
     */
    public function postsOfMarked(Request $request, $zone)
    {
        $userRepository = new UserRepository();
        $userId = $userRepository->getUserIdByZone($zone);
        if (!$userId)
        {
            return $this->resErrNotFound('找不到用户');
        }

        return $this->resOK($userRepository->markedPost($userId));
    }

    /**
     * 用户反馈
     *
     * @Post("/user/feedback")
     *
     * @Transaction({
     *      @Request({"type": "反馈的类型", "desc": "反馈内容，最多120字"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"})
     * })
     */
    public function feedback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|integer',
            'desc' => 'required|max:120',
            'ua' => 'required'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();

        Feedback::create([
            'type' => $request->get('type'),
            'desc' => $request->get('desc'),
            'ua' => $request->get('ua'),
            'user_id' => $userId
        ]);

        return $this->resNoContent();
    }

    /**
     * 用户消息列表
     *
     * @Get("/user/notifications/list")
     *
     * @Transaction({
     *      @Request({"minId": "看过的最小id"}),
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "消息列表"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户"})
     * })
     */
    public function notifications(Request $request)
    {
        $userId = $this->getAuthUserId();

        $minId = $request->get('minId') ?: 0;
        $take = 10;

        $repository = new UserRepository();
        $data = $repository->getNotifications($userId, $minId, $take);

        return $this->resOK($data);
    }

    /**
     * 用户未读消息个数
     *
     * @Get("/user/notifications/count")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "未读个数"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户"})
     * })
     */
    public function waitingReadNotifications()
    {
        $repository = new UserRepository();
        $count = $repository->getNotificationCount($this->getAuthUserId());

        return $this->resOK($count);
    }

    /**
     * 读取某条消息
     *
     * @Post("/user/notifications/read")
     *
     * @Transaction({
     *      @Request({"id": "消息id"}),
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204)
     * })
     */
    public function readNotification(Request $request)
    {
        $id = $request->get('id');
        $notification = Notifications::find($id);

        if (is_null($notification))
        {
            return $this->resNoContent();
        }

        if (intval($notification['to_user_id']) !== $this->getAuthUserId())
        {
            return $this->resNoContent();
        }

        Notifications::where('id', $id)->update([
            'checked' => true
        ]);

        return $this->resNoContent();
    }

    /**
     * 清空未读消息
     *
     * @Post("/user/notifications/clear")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204)
     * })
     */
    public function clearNotification()
    {
        Notifications::where('to_user_id', $this->getAuthUserId())->update([
            'checked' => true
        ]);

        return $this->resNoContent();
    }

    /**
     * 用户应援的角色列表
     *
     * @Get("/user/`zone`/followed/role")
     *
     * @Parameters({
     *      @Parameter("page", description="页码", type="integer", default=0, required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"list": "角色列表", "total": "总数", "noMore": "没有更多"}}),
     *      @Response(404, body={"code": 40401, "message": "该用户不存在"})
     * })
     */
    public function followedRoles(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('该用户不存在');
        }

        $repository = new UserRepository();
        $ids = $repository->rolesIds($userId);
        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'total' => 0,
                'noMore' => true
            ]);
        }

        $page = $request->get('page') ?: 0;
        $take = 10;
        $idsObject = $this->filterIdsByPage($ids, $page, $take);

        $cartoonRoleRepository = new CartoonRoleRepository();
        $list = $cartoonRoleRepository->list($idsObject['ids']);

        foreach ($list as $i => $item)
        {
            $list[$i]['has_star'] = $cartoonRoleRepository->checkHasStar($item['id'], $userId);
        }

        $transformer = new CartoonRoleTransformer();

        return $this->resOK([
            'list' => $transformer->userList($list),
            'total' => $idsObject['total'],
            'noMore' => $idsObject['noMore']
        ]);
    }

    // TODO：trending service
    // TODO：API Doc
    public function imageList(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('该用户不存在');
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 12;
        $tags = $request->get('tags') ?: 0;
        $size = $request->get('size') ?: 0;
        $bangumiId = intval($request->get('bangumiId'));
        $creator = intval($request->get('creator'));
        $sort = $request->get('sort') ?: 'new';
        $imageRepository = new ImageRepository();

        $ids = Image::whereRaw('user_id = ? and album_id = 0 and image_count <> 1', [$userId])
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
            ->when($size, function ($query) use ($size)
            {
                return $query->where('size_id', $size);
            })
            ->when($bangumiId !== -1, function ($query) use ($bangumiId)
            {
                return $query->where('bangumi_id', $bangumiId);
            })
            ->when($creator !== -1, function ($query) use ($creator)
            {
                return $query->where('creator', $creator);
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

        $list = $imageRepository->list($ids);
        $visitorId = $this->getAuthUserId();

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
     * 用户的图片相册列表
     *
     * @Get("/user/images/albums")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "相册列表"})
     * })
     */
    public function imageAlbums()
    {
        $userId = $this->getAuthUserId();

        $repository = new UserRepository();

        $list = $repository->imageAlbums($userId);

        if (empty($list))
        {
            return $this->resOK([]);
        }

        $transformer = new ImageTransformer();

        return $this->resOK($transformer->albums($list));
    }

    public function fakers()
    {
        $users = User::withTrashed()
            ->where('faker', 1)
            ->orderBy('id', 'DESC')
            ->get();

        return $this->resOK($users);
    }

    public function fakerReborn(Request $request)
    {
        $phone = $request->get('phone');

        $count = User::withTrashed()->where('phone', $phone)->count();
        if ($count)
        {
            return $this->resErrBad('手机号已被占用');
        }

        $userId = $request->get('id');
        User::where('id', $userId)
            ->update([
                'phone' => $phone,
                'faker' => 0
            ]);

        Redis::DEL('user_' . $userId);

        return $this->resNoContent();
    }

    public function coinDescList(Request $request)
    {
        $curPage = $request->get('cur_page') ?: 0;
        $toPage = $request->get('to_page') ?: 1;
        $take = $request->get('take') ?: 10;

        $list = User::orderBy('coin_count', 'DESC')
            ->select('nickname', 'id', 'zone', 'coin_count', 'faker')
            ->take(($toPage - $curPage) * $take)
            ->skip($curPage * $take)
            ->get();

        $totalUserCount = new TotalUserCount();

        return $this->resOK([
            'list' => $list,
            'total' => $totalUserCount->get()
        ]);
    }

    public function addUserToTrial(Request $request)
    {
        $userId = $request->get('id');

        $user = User::find($userId);

        if (is_null($user))
        {
            $this->resErrNotFound('不存在的用户');
        }

        User::where('id', $userId)
            ->update([
                'state' => 1
            ]);

        return $this->resNoContent();
    }

    public function blockUser(Request $request)
    {
        $userId = $request->get('id');
        User::where('id', $userId)->delete();
        $searchService = new Search();
        $searchService->delete($userId, 'user');

        Redis::DEL('user_' . $userId);

        return $this->resNoContent();
    }

    public function recoverUser(Request $request)
    {
        $userId = $request->get('id');

        User::withTrashed()->where('id', $userId)->restore();
        $user = User::withTrashed()->where('id', $userId)->first();

        $searchService = new Search();
        $searchService->create(
            $userId,
            $user->nickname . ',' . $user->zone,
            'user'
        );

        return $this->resNoContent();
    }

    public function feedbackList()
    {
        $list = Feedback::where('stage', 0)->get();

        return $this->resOK($list);
    }

    public function readFeedback(Request $request)
    {
        Feedback::where('id', $request->get('id'))->update([
            'stage' => 1
        ]);

        return $this->resNoContent();
    }

    public function adminUsers()
    {
        $users = User::where('is_admin', 1)
            ->select('id', 'zone', 'nickname')
            ->get();

        return $this->resOK($users);
    }

    public function removeAdmin(Request $request)
    {
        $userId = $this->getAuthUserId();
        $id = $request->get('id');

        if (intval($id) === 1 || $userId !== 1)
        {
            return $this->resErrRole();
        }

        User::whereRaw('id = ? and is_admin = 1', [$id])
            ->update([
                'is_admin' => 0
            ]);

        return $this->resNoContent();
    }

    public function addAdmin(Request $request)
    {
        $userId = $this->getAuthUserId();

        if ($userId !== 1)
        {
            return $this->resErrRole();
        }

        User::whereRaw('id = ? and is_admin = 0', [$request->get('id')])
            ->update([
                'is_admin' => 1
            ]);

        return $this->resNoContent();
    }
}