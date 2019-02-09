<?php
/**
 * Created by PhpStorm.
 * User: 23002
 * Date: 2017/11/25
 * Time: 0:20
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\Activity\UserActivity;
use App\Api\V1\Services\Counter\Stats\TotalUserCount;
use App\Api\V1\Services\LightCoinService;
use App\Api\V1\Services\Role;
use App\Api\V1\Services\Tag\Base\UserBadgeService;
use App\Api\V1\Services\Toggle\Image\ImageMarkService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Services\Toggle\Question\AnswerMarkService;
use App\Api\V1\Services\Toggle\Score\ScoreMarkService;
use App\Api\V1\Services\Toggle\Video\VideoMarkService;
use App\Api\V1\Services\UserLevel;
use App\Api\V1\Services\VirtualCoinService;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Feedback;
use App\Models\LightCoinRecord;
use App\Models\Notifications;
use App\Models\SystemNotice;
use App\Models\User;
use App\Api\V1\Repositories\UserRepository;
use App\Models\UserSign;
use App\Models\VirtualCoin;
use App\Services\OpenSearch\Search;
use App\Services\Trial\UserIpAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $userRepository = new UserRepository();
        $userId = $this->getAuthUserId();

        if ($userRepository->daySigned($userId))
        {
            return $this->resErrRole('已签到');
        }

        $lightCoinService = new LightCoinService();
        $virtualCoinService = new VirtualCoinService();
        $result = $lightCoinService->daySign($userId);
        $virtualCoinService->daySign($userId);
        if (!$result)
        {
            return $this->resErrServiceUnavailable('系统维护中');
        }

        UserSign::create([
            'user_id' => $userId,
            'migration_state' => 2
        ]);

        User::where('id', $userId)->increment('coin_count', 1);
        Redis::DEL('user_' . $userId . '_day_signed_' . date('y-m-d', time()));

        $userLevel = new UserLevel();
        $exp = $userLevel->change($userId, 2, false);

        return $this->resOK([
            'exp' => $exp,
            'message' => "签到成功，经验+{$exp}"
        ]);
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
    public function show(Request $request, $zone)
    {
        $userRepository = new UserRepository();
        $userId = $userRepository->getUserIdByZone($zone);
        if (is_null($userId))
        {
            return $this->resErrNotFound('该用户不存在');
        }

        $userTransformer = new UserTransformer();
        $user = $userRepository->item($userId, true);
        if (is_null($user))
        {
            return $this->resErrNotFound('该用户不存在');
        }

        if ($user['deleted_at'] && !$request->get('showDelete'))
        {
            if ($user['state'])
            {
                return $this->resErrLocked();
            }

            return $this->resErrNotFound('该用户不存在');
        }

        $visitUserId = $this->getAuthUserId();
        $searchService = new Search();
        if ($searchService->checkNeedMigrate('user', $userId))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('user', $userId));
            dispatch($job);
        }

        $userLevel = new UserLevel();
        $user['level'] = $userLevel->convertExpToLevel($user['exp']);

        $userActivityService = new UserActivity();
        $user['power'] = $userActivityService->get($userId);
        $userBadgeService = new UserBadgeService();
        $user['badge'] = $userBadgeService->getUserBadges($userId);
        $user['share_data'] = [
            'title' => $user['nickname'],
            'desc' => $user['signature'],
            'link' => $this->createShareLink('user', $zone, $visitUserId),
            'image' => "{$user['avatar']}-share120jpg"
        ];

        return $this->resOK($userTransformer->show($user));
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
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"}),
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

        $repository = new Repository();
        $val = $repository->convertImagePath($request->get('url'));

        User::where('id', $userId)->update([
            $key => $val
        ]);

        Redis::DEL('user_'.$userId);

        $job = (new \App\Jobs\Trial\User\Image($userId, $key));
        dispatch($job);

        return $this->resOK();
    }

    /**
     * 更新用户资料中文本
     *
     * > 性别对应：
     *  0：未知，1：男，2：女，3：伪娘，4：药娘，5：扶她
     *
     * @Post("/user/setting/profile")
     *
     * @Parameters({
     *      @Parameter("sex", description="性别，必填", type="integer", required=true),
     *      @Parameter("signature", description="用户签名，最多150字", type="string", required=true),
     *      @Parameter("nickname", description="用户昵称，最多14字符（1个汉字占2个字符）", type="string", required=true),
     *      @Parameter("birthday", description="用户生日，秒为单位的时间戳", type="number", required=true),
     *      @Parameter("birth_secret", description="生日是否保密", type="boolean", required=true),
     *      @Parameter("sex_secret", description="性别是否保密", type="boolean", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"}),
     *      @Response(204)
     * })
     */
    public function profile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sex' => 'required',
            'signature' => 'string|min:0|max:150',
            'nickname' => 'required|min:1|max:14',
            'birth_secret' => 'required|boolean',
            'birthday' => 'required',
            'sex_secret' => 'required|boolean'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();
        $birthday = $request->get('birthday') ? date('Y-m-d H:m:s', (int)$request->get('birthday')) : null;

        User::where('id', $userId)->update([
            'nickname' => $request->get('nickname'),
            'signature' => Purifier::clean($request->get('signature')),
            'sex' => $request->get('sex'),
            'sex_secret' => $request->get('sex_secret'),
            'birthday' => $birthday,
            'birth_secret' => $request->get('birth_secret')
        ]);

        Redis::DEL('user_'.$userId);

        $job = (new \App\Jobs\Trial\User\Text($userId));
        dispatch($job);

        return $this->resOK();
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
     * 用户回复的帖子列表
     *
     * @Get("/user/`zone`/posts/reply")
     *
     * @Parameter("page", description="页码", type="integer", required=true),
     *
     * @Transaction({
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

        $page = $request->get('page') ?: 0;
        $take = 10;
        $idsObject = $this->filterIdsByPage($ids, $page, $take);

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
        if ($page == 0 && count($result) == 0)
        {
            $idsObject['total'] = 0;
        }

        return $this->resOK([
            'list' => $result,
            'total' => $idsObject['total'],
            'noMore' => $idsObject['noMore']
        ]);
    }

    /**
     * 获取当前用户的收藏列表
     *
     * @Get("/user/bookmarks")
     *
     * @Parameter("type", description="类型", type="string", required=true),
     * @Parameter("page", description="页码", type="integer", required=true, default=0),
     * @Parameter("take", description="个数", type="integer", required=false, default=15),
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "data"}),
     *      @Response(403, body={"code": 40003, "message": "请求参数错误"})
     * })
     */
    public function bookmarks(Request $request)
    {
        $type = $request->get('type');
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 15;
        $userId = $this->getAuthUserId();

        if (!in_array($type, ['post', 'image', 'video', 'score', 'answer']))
        {
            return $this->resErrBad();
        }

        $markService = null;
        $repository = null;
        if ($type === 'post')
        {
            $markService = new PostMarkService();
            $repository = new PostRepository();
        }
        else if ($type === 'image')
        {
            $markService = new ImageMarkService();
            $repository = new ImageRepository();
        }
        else if ($type === 'video')
        {
            $markService = new VideoMarkService();
            $repository = new VideoRepository();
        }
        else if ($type === 'score')
        {
            $markService = new ScoreMarkService();
            $repository = new ScoreRepository();
        }
        else if ($type === 'answer')
        {
            $markService = new AnswerMarkService();
            $repository = new AnswerRepository();
        }
        else
        {
            return $this->resErrBad();
        }
        $idsObj = $markService->usersDoIds($userId, $page, $take);
        if (!$idsObj['total'])
        {
            return $this->resOK([
                'list' => [],
                'total' => 0,
                'noMore' => true
            ]);
        }

        $list = [];
        foreach ($idsObj['ids'] as $id => $time)
        {
            $item = $repository->item($id, true);
            if (is_null($item))
            {
                continue;
            }
            $item['created_at'] = $time;
            $list[] = $item;
        }

        return $this->resOK([
            'list' => $list,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

    /**
     * 用户反馈
     *
     * @Post("/user/feedback")
     *
     * @Transaction({
     *      @Request({"type": "反馈的类型", "desc": "反馈内容，最多120字", "ua": "用户的设备信息"}),
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

        return $this->resOK();
    }

    /**
     * 用户消息列表
     *
     * @Get("/user/notification/list")
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

        $userRepository = new UserRepository();
        $result = $userRepository->getNotifications($userId, $minId, $take);
        if (!$minId)
        {
            $user = $this->getAuthUser();
            $systemNoticeCount = $userRepository->computedSystemNoticeCount($user->last_notice_read_id);
            $result['system_count'] = $systemNoticeCount;
            if ($systemNoticeCount)
            {
                $result['lastest_notice'] = $userRepository->Cache('system_notice_lastest_item', function ()
                {
                    $notice = SystemNotice
                        ::orderBy('id', 'DESC')
                        ->first()
                        ->toArray();

                    return [
                        'id' => $notice['id'],
                        'title' => $notice['title'],
                        'created_at' => $notice['created_at']
                    ];
                });
            }
        }

        return $this->resOK($result);
    }

    // 用户邀请的人
    public function userInviteUsers(Request $request)
    {
        $userId = $request->get('id');
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 15;

        $userRepository = new UserRepository();
        $userIds = $userRepository->RedisList("user_{$userId}_invite_users", function () use ($userId)
        {
            return VirtualCoin
                ::where('channel_type', 1)
                ->where('user_id', $userId)
                ->orderBy('created_at', 'DESC')
                ->pluck('about_user_id')
                ->toArray();

        }, 0, -1, 'm');

        $idsObj = $userRepository->filterIdsByPage($userIds, $page, $take);

        if (empty($idsObj['ids']))
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true,
                'total' => 0
            ]);
        }

        $users = $userRepository->list($idsObj['ids']);
        $userTransformer = new UserTransformer();

        return $this->resOK([
            'list' => $userTransformer->list($users),
            'noMore' => $idsObj['noMore'],
            'total' => $idsObj['total']
        ]);
    }

    /**
     * 用户未读消息个数
     *
     * @Get("/user/notification/count")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "未读个数"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户"})
     * })
     */
    public function waitingReadNotifications()
    {
        $user = $this->getAuthUser();
        $userRepository = new UserRepository();
        $count = $userRepository->getNotificationCount($this->getAuthUserId());
        $count = $count < 0 ? 0 : $count;
        // 计算系统消息个数
        $systemNoticeCount = $userRepository->computedSystemNoticeCount($user->last_notice_read_id);

        return $this->resOK($count + $systemNoticeCount);
    }

    /**
     * 读取某条消息
     *
     * @Post("/user/notification/read")
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
            return $this->resOK();
        }

        $userId =  $this->getAuthUserId();

        if (intval($notification['to_user_id']) !== $userId)
        {
            return $this->resOK();
        }

        Notifications::where('id', $id)->update([
            'checked' => true
        ]);

        Redis::DEL('notification-' . $id);
        if (Redis::EXISTS('user_' . $userId . '_notification_count'))
        {
            Redis::INCRBY('user_' . $userId . '_notification_count', -1);
        }

        return $this->resOK();
    }

    /**
     * 清空未读消息
     *
     * @Post("/user/notification/clear")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204)
     * })
     */
    public function clearNotification()
    {
        $user = $this->getAuthUser();
        $userId = $user->id;

        $ids = Notifications
            ::where('to_user_id', $userId)
            ->pluck('id')
            ->toArray();

        if (!$ids)
        {
            Redis::DEL('user-' . $userId . '-notification-ids');
            Redis::SET('user_' . $userId . '_notification_count', 0);
            return $this->resOK();
        }

        Notifications
            ::where('to_user_id', $userId)
            ->update([
                'checked' => true
            ]);

        $userRepository = new UserRepository();
        $systemNoticeCount = $userRepository->computedSystemNoticeCount($user->last_notice_read_id);
        if ($systemNoticeCount)
        {
            $maxNoticeId = SystemNotice
                ::orderBy('id', 'DESC')
                ->pluck('id')
                ->first();

            User::where('id', $userId)
                ->update([
                    'last_notice_read_id' => $maxNoticeId
                ]);
        }

        Redis::pipeline(function ($pipe) use ($ids, $userId)
        {
            foreach ($ids as $id)
            {
                $pipe->DEL('notification-' . $id);
            }

            Redis::DEL('user-' . $userId . '-notification-ids');
            Redis::SET('user_' . $userId . '_notification_count', 0);
        });

        return $this->resOK();
    }

    /**
     * 用户的虚拟币记录列表
     *
     * @Get("/user/transactions")
     *
     * @Transaction({
     *      @Request({"page": "页码", "default": 0, "required": true}),
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "消息列表"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户"})
     * })
     */
    public function transactions(Request $request)
    {
        $take = $request->get('take') ?: 20;
        $page = $request->get('page') ?: 0;
        $userId = $this->getAuthUserId();

        $virtualCoinService = new VirtualCoinService();
        $result = $virtualCoinService->getUserRecord($userId, $page, $take);
        if ($page == 0)
        {
            $result['balance'] = $virtualCoinService->getUserBalance($userId);
        }

        return $this->resOK($result);
    }

    // 给后台用的，分析用户邀请的人都是什么样
    public function userInviteList(Request $request)
    {
        $id = $request->get('id');

        $userRepository = new UserRepository();
        $user = $userRepository->item($id);
        if (is_null($user))
        {
            return $this->resErrNotFound();
        }

        $ids = LightCoinRecord
            ::where('to_user_id', $id)
            ->where('to_product_type', 1)
            ->pluck('from_user_id');

        if (!$ids)
        {
            return $this->resOK([]);
        }

        $users = [];
        $userLevel = new UserLevel();
        $userActivityService = new UserActivity();
        foreach ($ids as $userId)
        {
            $user = $userRepository->item($userId);
            if (is_null($user))
            {
                continue;
            }
            $user['level'] = $userLevel->convertExpToLevel($user['exp']);
            $user['power'] = $userActivityService->get($userId);
            $users[] = $user;
        }

        return $this->resOK($users);
    }

    // 后台给用户送团子
    public function giveUserMoney(Request $request)
    {
        $currentUserId = $this->getAuthUserId();
        if ($currentUserId != 1)
        {
            return $this->resErrRole();
        }
        $userId = $request->get('id');
        $amount = $request->get('amount');
        $state = $request->get('state');

        $lightCoinService = new LightCoinService();
        $virtualCoinService = new VirtualCoinService();
        if ($state == 0)
        {
            $lightCoinService->coinGift($userId, $amount);
            $virtualCoinService->coinGift($userId, $amount);
        }
        else if ($state == 1)
        {
            $lightCoinService->lightGift($userId, $amount);
            $virtualCoinService->lightGift($userId, $amount);
        }

        return $this->resOK();
    }

    // 获取推荐用户
    public function recommendedUsers()
    {
        $userRepository = new UserRepository();

        $ids = $userRepository->Cache('recommended-activity-user-ids', function () use ($userRepository)
        {
            $ids = LightCoinRecord
                ::whereIn('to_product_type', [4, 5, 6, 7, 8, 9])
                ->select(DB::raw('count(*) as count, from_user_id'))
                ->groupBy('from_user_id')
                ->orderBy('count', 'DESC')
                ->take(20)
                ->get()
                ->toArray();

            $userActivityService = new UserActivity();
            $result = [];
            foreach ($ids as $item)
            {
                $power = $userActivityService->get($item['from_user_id']);
                if (!$power)
                {
                    continue;
                }

                $result[] = [
                    'id' => $item['from_user_id'],
                    'power' => $power + (int)$item['count']
                ];
            }

            $seenIds = array_map(function ($item)
            {
                return $item['from_user_id'];
            }, $ids);
            $rencentIds = array_slice($userActivityService->recentIds(), 0, 20);

            foreach ($rencentIds as $id)
            {
                if (in_array($id, $seenIds))
                {
                    continue;
                }
                $power = $userActivityService->get($id);
                $result[] = [
                    'id' => $id,
                    'power' => $power
                ];
            }

            return array_values(array_sort($result, function ($value)
            {
                return -$value['power'];
            }));
        });

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $result = [];
        foreach ($ids as $item)
        {
            $user = $userRepository->item($item['id']);
            if (is_null($user) || $user['banned_to'])
            {
                continue;
            }
            $result[] = $user;
        }
        $userTransformer = new UserTransformer();

        return $this->resOK($userTransformer->recommended($result));
    }

    // 获取用户卡片信息
    public function userCard(Request $request)
    {
        $userId = $request->get('id');
        $userRepisotory = new UserRepository();

        $user = $userRepisotory->item($userId);
        if (is_null($user))
        {
            return $this->resErrNotFound();
        }

        $userLevel = new UserLevel();
        $user['level'] = $userLevel->convertExpToLevel($user['exp']);

        $userActivityService = new UserActivity();
        $user['power'] = $userActivityService->get($userId);

        $userBadgeService = new UserBadgeService();
        $user['badge'] = $userBadgeService->getUserBadges($userId);

        $userTransformer = new UserTransformer();
        return $this->resOK($userTransformer->card($user));
    }

    // 后台获取用户详情
    public function getUserInfo(Request $request)
    {
        $type = $request->get('type');
        $value = $request->get('value');
        if (!$type || !$value)
        {
            return $this->resErrBad();
        }

        $userIpAddress = new UserIpAddress();
        $userRepository = new UserRepository();
        $userLevel = new UserLevel();
        $userActivityService = new UserActivity();
        $lightCoinServce = new LightCoinService();

        if ($type === 'ip_address')
        {
            $userIds = $userIpAddress->addressUsers($value);
            $users = $userRepository->list($userIds, true);
            foreach ($users as $i => $user)
            {
                $users[$i]['level'] = $userLevel->convertExpToLevel($user['exp']);
                $users[$i]['power'] = $userActivityService->get($user['id']);
                $users[$i]['banlacen'] = $lightCoinServce->getUserBanlance($user['id']);
            }
            return $this->resOK($users);
        }

        if ($type !== 'id')
        {
            $userId = User
                ::where($type, $value)
                ->pluck('id')
                ->first();
        }
        else
        {
            $userId = $value;
        }

        if (!$userId)
        {
            return $this->resErrNotFound();
        }

        $user = $userRepository->item($userId, true);
        if (is_null($user))
        {
            return $this->resOK(null);
        }
        $banlance = User
            ::where('id', $userId)
            ->select('coin_count_v2', 'light_count')
            ->first();

        $user['coin_count'] = $banlance->coin_count_v2;
        $user['light_count'] = $banlance->light_count;
        $user['ip_address'] = $userIpAddress->userIps($userId);
        $user['level'] = $userLevel->convertExpToLevel($user['exp']);
        $user['power'] = $userActivityService->get($userId);
        $user['invite_count'] = LightCoinRecord
            ::where('to_product_type', 1)
            ->where('to_user_id', $userId)
            ->groupBy('order_id')
            ->count();
        $user['banlacen'] = $lightCoinServce->getUserBanlance($user['id']);

        return $this->resOK($user);
    }

    // 营销号列表
    public function fakers()
    {
        $users = User::withTrashed()
            ->where('faker', 1)
            ->orderBy('id', 'DESC')
            ->get();

        return $this->resOK($users);
    }

    // 1个 IP 有多个用户的列表
    public function matrixUsers(Request $request)
    {
        $curPage = $request->get('cur_page') ?: 0;
        $toPage = $request->get('to_page') ?: 1;
        $take = $request->get('take') ?: 10;

        $userIpAddress = new UserIpAddress();
        $ipObj = $userIpAddress->matrixUserIp($curPage, ($toPage - $curPage) * $take);
        $list = [];
        $ids = $ipObj['ids'];
        foreach ($ids as $ip => $count)
        {
            $list[] = [
                'ip' => $ip,
                'count' => $count,
                'blocked' => $userIpAddress->checkIpIsBlocked($ip)
            ];
        }

        return $this->resOK([
            'list' => $list,
            'total' => $ipObj['total'],
            'noMore' => $ipObj['noMore']
        ]);
    }

    // TODO：查找多重用户，直接超时了，没法用
    public function matrixUserGroup()
    {
        $ipAddressService = new UserIpAddress();
        $ipList = $ipAddressService->someQuestionIP();
        $groups = [];
        foreach ($ipList as $ip)
        {
            $groups[] = $ipAddressService->addressUsers($ip);
        }

        $loop = true;
        while ($loop)
        {
            $needLoop = false;
            // 遍历所有的分组
            foreach ($groups as $i => $group)
            {
                // 遍历所有的 ip
                foreach ($group as $ip)
                {
                    foreach ($groups as $j => $list)
                    {
                        if ($i == $j || empty($list))
                        {
                            continue;
                        }
                        if (in_array($ip, $list))
                        {
                            $groups[$i] = array_merge($groups[$i], $groups[$j]);
                            $groups[$j] = [];
                            $needLoop = true;
                        }
                    }
                }
            }
            if (!$needLoop)
            {
                $loop = false;
            }
        }

        $userRepository = new UserRepository();
        $userTransformer = new UserTransformer();
        $groups = array_filter($groups);
        foreach ($groups as $i => $userIds)
        {
            $userIds = array_unique($userIds);
            $users = $userTransformer->list($userRepository->list($userIds));
            $groups[$i] = $users;
        }

        return $this->resOK($groups);
    }

    // 删除不存在用户的 IP 地址
    public function clearNoOneIpAddress(Request $request)
    {
        $ip = $request->get('ip');
        if (!$ip)
        {
            return $this->resOK('不存在的IP');
        }

        $data = DB
            ::table('user_ip')
            ->where('ip_address', $ip)
            ->distinct()
            ->pluck('id', 'user_id')
            ->toArray();

        $deleteCount = 0;
        foreach ($data as $userId => $id)
        {
            if (!DB::table('users')->where('id', $userId)->count())
            {
                DB::table('user_ip')->where('id', $id)->delete();
                $deleteCount++;
            }
        }

        return $this->resOK('删除用户个数：' . $deleteCount);
    }

    // 后台获取被封禁的用户ip列表
    public function getBlockedUserIpAddress()
    {
        $userIpAddress = new UserIpAddress();

        return $this->resOK($userIpAddress->blockedList());
    }

    // 后台封禁用户ip
    public function blockUserByIp(Request $request)
    {
        $ipAddress = $request->get('ip_address');

        $userIpAddress = new UserIpAddress();
        $userIpAddress->blockUserByIp($ipAddress);

        return $this->resNoContent();
    }

    // 后台解禁用户的ip
    public function recoverUserIp(Request $request)
    {
        $ipAddress = $request->get('ip_address');

        $userIpAddress = new UserIpAddress();
        $userIpAddress->recoverUser($ipAddress);

        return $this->resNoContent();
    }

    // 认领营销号
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

    // 团子用户排行榜
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

    // 把用户加入到审核列表
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

    // 用户反馈列表
    public function feedbackList()
    {
        $list = Feedback::where('stage', 0)->get();

        return $this->resOK($list);
    }

    // 读取用户反馈列表
    public function readFeedback(Request $request)
    {
        Feedback::where('id', $request->get('id'))->update([
            'stage' => 1
        ]);

        return $this->resNoContent();
    }

    // 管理员列表
    public function adminUsers()
    {
        $users = User::where('is_admin', 1)
            ->select('id', 'zone', 'nickname')
            ->get();

        return $this->resOK($users);
    }

    // 撤销管理员
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

        $role = new Role();
        $role->clear($id);

        return $this->resNoContent();
    }

    // 添加管理员
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

    // 获取用户的交易记录
    public function getUserCoinTransactions(Request $request)
    {
        $curPage = $request->get('cur_page') ?: 0;
        $toPage = $request->get('to_page') ?: 1;
        $take = $request->get('take') ?: 10;
        $userId = $request->get('id');
        $lightCoinService = new LightCoinService();
        $result = $lightCoinService->getUserRecord($userId, $curPage, ($toPage - $curPage) * $take);

        return $this->resOK($result);
    }

    // 用户提现
    public function withdrawal(Request $request)
    {
        $adminId = $this->getAuthUserId();
        if ($adminId !== 1)
        {
            return $this->resErrRole();
        }

        $userId = $request->get('id');
        $money = $request->get('money');
        $lightCoinService = new LightCoinService();
        $lightCoinService->withdraw($userId, $money);
        $virtualCoinService = new VirtualCoinService();
        $result = $virtualCoinService->withdraw($userId, $money);
        if (!$result)
        {
            $lightCoinService->resErrServiceUnavailable('提现失败');
        }

        Redis::DEL('user_' . $userId);

        return $this->resNoContent();
    }

    // 用户审核列表
    public function trials()
    {
        $users = User
            ::withTrashed()
            ->where('state', '<>', 0)
            ->orderBy('updated_at', 'DESC')
            ->get();

        return $this->resOK($users);
    }

    // 封禁用户
    public function ban(Request $request)
    {
        $userId = $request->get('id');
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'state' => 0,
                'deleted_at' => Carbon::now()
            ]);

        $job = (new \App\Jobs\Search\Index('D', 'user', $userId));
        dispatch($job);

        Redis::DEL('user_' . $userId);

        return $this->resNoContent();
    }

    // 通过用户
    public function pass(Request $request)
    {
        User::where('id', $request->get('id'))->update([
            'state' => 0
        ]);

        return $this->resNoContent();
    }

    // 解禁用户
    public function recover(Request $request)
    {
        $userId = $request->get('id');
        $userRepository = new UserRepository();
        $user = $userRepository->item($userId, true);
        if (is_null($user))
        {
            return $this->resErrNotFound();
        }

        User::withTrashed()->where('id', $userId)->restore();

        $userRepository->migrateSearchIndex('C', $userId);
        Redis::DEL('user_' . $userId);

        return $this->resNoContent();
    }

    // 删除用户的某条数据
    public function deleteUserInfo(Request $request)
    {
        $userId = $request->get('id');
        User::where('id', $userId)
            ->update([
                $request->get('key') => $request->get('value') ?: ''
            ]);

        Redis::DEL('user_' . $userId);

        return $this->resNoContent();
    }

    // 被禁言的用户列表
    public function freezeUserList()
    {
        $list = User
            ::whereNotNull('banned_to')
            ->get();

        return $this->resOK($list);
    }

    // 禁言用户
    public function freezeUser(Request $request)
    {
        $userId = $request->get('id');
        $banned_to = $request->get('banned_to');

        User::where('id', $userId)
            ->update([
                'banned_to' => date('Y-m-d H:m:s', strtotime($banned_to))
            ]);

        Redis::DEL('user_'. $userId);

        return $this->resNoContent();
    }

    // 解禁用户
    public function freeUser(Request $request)
    {
        $userId = $request->get('id');

        User::where('id', $userId)
            ->update([
                'banned_to' => null
            ]);

        Redis::DEL('user_'. $userId);

        return $this->resNoContent();
    }
}
