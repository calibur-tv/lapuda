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
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""}),
     *      @Response(403, body={"code": 40301, "message": "今日已签到", "data": ""})
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

        $job = (new \App\Jobs\Search\User\Update($userId));
        dispatch($job);

        return $this->resNoContent();
    }

    /**
     * 更新用户资料中的图片
     *
     * @Post("/user/setting/image")
     *
     * @Transaction({
     *      @Request({"type": "avatar或banner", "url": "图片地址"}, headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误", "data": ""}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""})
     * })
     */
    public function image(Request $request)
    {
        $userId = $this->getAuthUserId();
        $key = $request->get('type');

        if (!in_array($key, ['avatar', 'banner']))
        {
            return $this->resErrParams();
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
     * 获取用户页面信息
     *
     * @Post("/user/${userZone}/show")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "用户信息对象"}),
     *      @Response(404, body={"code": 40401, "message": "该用户不存在", "data": ""})
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

    /**
     * 修改用户自己的信息
     *
     * @Post("/user/setting/profile")
     *
     * @Transaction({
     *      @Request({"sex": "性别: 0, 1, 2, 3, 4", "signature": "用户签名，最多20字", "nickname": "用户昵称，最多14个字符", "birthday": "以秒为单位的时间戳"}, headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(404, body={"code": 40104, "message": "该用户不存在", "data": ""})
     * })
     */
    public function profile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sex' => 'required',
            'signature' => 'string|min:0|max:20',
            'nickname' => 'required|min:1|max:14',
            'birthday' => 'required'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
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
     * 用去用户关注番剧的列表
     *
     * @Post("/user/${userZone}/followed/bangumi")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧列表"}),
     *      @Response(404, body={"code": 40104, "message": "该用户不存在", "data": ""})
     * })
     */
    public function followedBangumis($zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('该用户不存在');
        }

        $repository = new UserRepository();
        $follows = $repository->bangumis($userId);

        return $this->resOK($follows);
    }

    /**
     * 用户发布的帖子列表
     *
     * @Post("/user/${userZone}/posts/mine")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的postIds, 用','分割的字符串", "take": "获取的数量"}),
     *      @Response(200, body={"code": 0, "data": "帖子列表"}),
     *      @Response(404, body={"code": 40104, "message": "找不到用户", "data": ""})
     * })
     */
    public function postsOfMine(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('找不到用户');
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $userRepository = new UserRepository();
        $ids = $userRepository->minePostIds($userId);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $postRepository = new PostRepository();
        $postTransformer = new PostTransformer();
        $list = $postRepository->list(array_slice(array_diff($ids, $seen), 0, $take));

        return $this->resOK($postTransformer->usersMine($list));
    }

    /**
     * 用户回复的帖子列表
     *
     * @Post("/user/${userZone}/posts/reply")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的postIds, 用','分割的字符串", "take": "获取的数量"}),
     *      @Response(200, body={"code": 0, "data": "帖子列表"}),
     *      @Response(404, body={"code": 40104, "message": "找不到用户", "data": ""})
     * })
     */
    public function postsOfReply(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('找不到用户');
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $userRepository = new UserRepository();
        $ids = $userRepository->replyPostIds($userId);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $ids = array_slice(array_diff($ids, $seen), 0, $take);
        $data = [];
        foreach ($ids as $id)
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

        return $this->resOK($result);
    }

    /**
     * 用户喜欢的帖子列表
     *
     * @Post("/user/${userZone}/posts/mine")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的postIds, 用','分割的字符串", "take": "获取的数量"}),
     *      @Response(200, body={"code": 0, "data": "帖子列表"}),
     *      @Response(404, body={"code": 40104, "message": "找不到用户", "data": ""})
     * })
     */
    public function postsOfLiked(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('找不到用户');
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $userRepository = new UserRepository();
        $ids = $userRepository->likedPostIds($userId);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $postRepository = new PostRepository();
        $postTransformer = new PostTransformer();
        $list = $postRepository->list(array_slice(array_diff($ids, $seen), 0, $take));

        return $this->resOK($postTransformer->userLike($list));
    }

    /**
     * 用户收藏的帖子列表
     *
     * @Post("/user/${userZone}/posts/mine")
     *
     * @Transaction({
     *      @Request({"seenIds": "看过的postIds, 用','分割的字符串", "take": "获取的数量"}),
     *      @Response(200, body={"code": 0, "data": "帖子列表"}),
     *      @Response(404, body={"code": 40104, "message": "找不到用户", "data": ""})
     * })
     */
    public function postsOfMarked(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('找不到用户');
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $userRepository = new UserRepository();
        $ids = $userRepository->markedPostIds($userId);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $postRepository = new PostRepository();
        $postTransformer = new PostTransformer();
        $list = $postRepository->list(array_slice(array_diff($ids, $seen), 0, $take));

        return $this->resOK($postTransformer->userMark($list));
    }

    /**
     * 用户反馈
     *
     * @Post("/user/feedback")
     *
     * @Transaction({
     *      @Request({"type": "反馈的类型", "desc": "反馈内容，最多120字"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误", "data": ""})
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
            return $this->resErrParams($validator->errors());
        }

        $userId = $this->getAuthUserId();

        Feedback::create([
            'type' => $request->get('type'),
            'desc' => $request->get('desc'),
            'ua' => $request->get('ua'),
            'user_id' => $userId
        ]);

        if ($userId)
        {
            $job = (new \App\Jobs\Search\User\Update($userId));
            dispatch($job);
        }

        return $this->resNoContent();
    }

    /**
     * 用户消息列表
     *
     * @Post("/user/notifications/list")
     *
     * @Transaction({
     *      @Request({"take": "获取个数", "minId": "看过的最小id"}),
     *      @Response(200, body={"code": 0, "data": "消息列表"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""})
     * })
     */
    public function notifications(Request $request)
    {
        $userId = $this->getAuthUserId();

        $minId = $request->get('minId') ?: 0;
        $take = $request->get('take') ?: 10;

        $repository = new UserRepository();
        $data = $repository->getNotifications($userId, $minId, $take);

        return $this->resOK($data);
    }

    /**
     * 用户未读消息个数
     *
     * @Post("/user/notifications/count")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "未读个数"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""})
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
     *      @Response(204),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""}),
     *      @Response(404, body={"code": 40401, "message": "不存在的消息", "data": ""}),
     *      @Response(403, body={"code": 40301, "message": "没有权限进行操作", "data": ""})
     * })
     */
    public function readNotification(Request $request)
    {
        $id = $request->get('id');
        $notification = Notifications::find($id);

        if (is_null($notification))
        {
            return $this->resErrNotFound('不存在的消息');
        }

        if (intval($notification['to_user_id']) !== $this->getAuthUserId())
        {
            return $this->resErrRole('没有权限进行操作');
        }

        Notifications::where('id', $id)->update([
            'checked' => true
        ]);

        return $this->resNoContent();
    }

    public function clearNotification()
    {
        Notifications::where('to_user_id', $this->getAuthUserId())->update([
            'checked' => true
        ]);

        return $this->resNoContent();
    }

    public function followedRoles(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('该用户不存在');
        }

        $page = $request->get('page') ?: 1;
        $take = $request->get('take') ?: config('website.list_count');
        $begin = $take * ($page - 1);

        $repository = new UserRepository();
        $ids = array_slice($repository->rolesIds($userId), $begin, $begin + $take);
        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $cartoonRoleRepository = new CartoonRoleRepository();
        $list = $cartoonRoleRepository->list($ids);

        foreach ($list as $i => $item)
        {
            $list[$i]['has_star'] = $cartoonRoleRepository->checkHasStar($item['id'], $userId);
        }

        $transformer = new CartoonRoleTransformer();

        return $this->resOK($transformer->userList($list));
    }

    public function imageList(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErrNotFound('该用户不存在');
        }

        $page = intval($request->get('page')) ?: 1;
        $take = intval($request->get('take')) ?: 12;
        $tags = $request->get('tags') ?: 0;
        $size = $request->get('size') ?: 0;
        $imageRepository = new ImageRepository();

        $ids = Image::where('user_id', $userId)
            ->whereIn('state', [1, 4])
            ->skip($take * ($page - 1))
            ->take($take)
            ->latest()
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

        $bangumiRepository = new BangumiRepository();
        $cartoonRoleRepository = new CartoonRoleRepository();

        $list = $imageRepository->list($ids);
        $visitorId = $this->getAuthUserId();
        $isMe = $visitorId === $userId;

        foreach ($list as $i => $item)
        {
            $list[$i]['bangumi'] = $list[$i]['bangumi_id'] ? $bangumiRepository->item($item['bangumi_id']) : null;
            $list[$i]['liked'] = $isMe ? false : $imageRepository->checkLiked($item['id'], $visitorId);
            $list[$i]['role'] = $item['role_id'] ? $cartoonRoleRepository->item($item['role_id']) : null;
        }

        $transformer = new ImageTransformer();

        return $this->resOK([
            'list' => $transformer->userList($list),
            'type' => $imageRepository->uploadImageTypes()
        ]);
    }
}