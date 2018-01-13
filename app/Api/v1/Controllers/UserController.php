<?php
/**
 * Created by PhpStorm.
 * User: 23002
 * Date: 2017/11/25
 * Time: 0:20
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Feedback;
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
     *      @Response(200, body={"code": 0, "data": ""}),
     *      @Response(401, body={"code": 401, "data": "未登录的用户"}),
     *      @Response(403, body={"code": 403, "data": "今日已签到"})
     * })
     */
    public function daySign()
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr('未登录的用户', 401);
        }

        $repository = new UserRepository();
        $userId = $user->id;

        if ($repository->daySigned($userId))
        {
            return $this->resErr('已签到', 403);
        }

        $repository->toggleCoin(false, $userId, $userId, 0);

        return $this->resOK();
    }

    /**
     * 更新用户资料中的图片
     *
     * @Post("/user/setting/image")
     *
     * @Transaction({
     *      @Request({"type": "avatar或banner", "url": "图片地址"}, headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": ""}),
     *      @Response(400, body={"code": 400, "data": "请求参数错误"}),
     *      @Response(401, body={"code": 401, "data": "未登录的用户"})
     * })
     */
    public function image(Request $request)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr('未登录的用户', 401);
        }

        $key = $request->get('type');

        if (!in_array($key, ['avatar', 'banner']))
        {
            return $this->resErr('请求参数错误', 400);
        }

        $val = $request->get('url');

        $user->update([
            $key => $val
        ]);

        $cache = 'user_'.$user->id;
        if (Redis::EXISTS($cache))
        {
            Redis::HSET($cache, $key, $val);
        }

        return $this->resOK();
    }

    /**
     * 获取用户页面信息
     *
     * @Post("/user/${userZone}/show")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "用户信息对象"}),
     *      @Response(404, body={"code": 404, "data": "该用户不存在"})
     * })
     */
    public function show($zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErr('该用户不存在', 404);
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
     *      @Response(200, body={"code": 0, "data": "用户信息对象"}),
     *      @Response(404, body={"code": 404, "data": "该用户不存在"})
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
            return $this->resErr('请求参数错误', 400);
        }

        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr('找不到用户', 404);
        }

        $user->update([
            'nickname' => Purifier::clean($request->get('nickname')),
            'signature' => Purifier::clean($request->get('signature')),
            'sex' => $request->get('sex'),
            'birthday' => $request->get('birthday')
        ]);

        Redis::DEL('user_'.$user->id);

        return $this->resOK();
    }

    /**
     * 用去用户关注番剧的列表
     *
     * @Post("/user/${userZone}/followed/bangumi")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧列表"}),
     *      @Response(404, body={"code": 404, "data": "该用户不存在"})
     * })
     */
    public function followedBangumis($zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErr('该用户不存在', 404);
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
     *      @Response(404, body={"code": 404, "data": "找不到用户"})
     * })
     */
    public function postsOfMine(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErr(['找不到用户'], 404);
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
     *      @Response(404, body={"code": 404, "data": "找不到用户"})
     * })
     */
    public function postsOfReply(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErr('找不到用户', 404);
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

        return $this->resOK($data);
    }

    /**
     * 用户反馈
     *
     * @Post("/user/feedback")
     *
     * @Transaction({
     *      @Request({"type": "反馈的类型", "desc": "反馈内容，最多120字"}),
     *      @Response(200, body={"code": 0, "data": ""}),
     *      @Response(400, body={"code": 400, "data": "请求参数错误"})
     * })
     */
    public function feedback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|integer',
            'desc' => 'required|max:120',
        ]);

        if ($validator->fails())
        {
            return $this->resErr('请求参数错误', 400);
        }

        Feedback::create([
            'type' => $request->get('type'),
            'desc' => $request->get('desc'),
            'user_id' => $this->getAuthUserId()
        ]);

        return $this->resOK();
    }
}