<?php
/**
 * Created by PhpStorm.
 * User: 23002
 * Date: 2017/11/25
 * Time: 0:20
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\Activity\UserActivity;
use App\Api\V1\Services\Counter\Stats\TotalUserCount;
use App\Api\V1\Services\UserLevel;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Feedback;
use App\Models\Notifications;
use App\Models\User;
use App\Api\V1\Repositories\UserRepository;
use App\Models\UserCoin;
use App\Models\UserSign;
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
        $repository = new UserRepository();
        $userId = $this->getAuthUserId();

        if ($repository->daySigned($userId))
        {
            return $this->resErrRole('已签到');
        }

        UserCoin::create([
            'from_user_id' => $userId,
            'user_id' => $userId,
            'type' => 8
        ]);

        UserSign::create([
            'user_id' => $userId
        ]);

        User::where('id', $userId)->increment('coin_count', 1);
        Redis::DEL('user_' . $userId . '_day_signed_' . date('y-m-d', time()));
        if (Redis::EXISTS('user_' . $userId . '_coin_sign'))
        {
            Redis::INCRBY('user_' . $userId . '_coin_sign', 1);
        }

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
    public function show($zone)
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

        if ($user['deleted_at'])
        {
            if ($user['state'])
            {
                return $this->resErrLocked();
            }

            return $this->resErrNotFound('该用户不存在');
        }

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

        return $this->resNoContent();
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
            'nickname' => Purifier::clean($request->get('nickname')),
            'signature' => Purifier::clean($request->get('signature')),
            'sex' => $request->get('sex'),
            'sex_secret' => $request->get('sex_secret'),
            'birthday' => $birthday,
            'birth_secret' => $request->get('birth_secret')
        ]);

        Redis::DEL('user_'.$userId);

        $job = (new \App\Jobs\Trial\User\Text($userId));
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

        return $this->resOK([
            'list' => $result,
            'total' => $idsObject['total'],
            'noMore' => $idsObject['noMore']
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

        return $this->resNoContent();
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

        $repository = new UserRepository();

        return $this->resOK($repository->getNotifications($userId, $minId, $take));
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
        $repository = new UserRepository();
        $count = $repository->getNotificationCount($this->getAuthUserId());

        return $this->resOK($count < 0 ? 0 : $count);
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
            return $this->resNoContent();
        }

        $userId =  $this->getAuthUserId();

        if (intval($notification['to_user_id']) !== $userId)
        {
            return $this->resNoContent();
        }

        Notifications::where('id', $id)->update([
            'checked' => true
        ]);

        Redis::DEL('notification-' . $id);
        if (Redis::EXISTS('user_' . $userId . '_notification_count'))
        {
            Redis::INCRBY('user_' . $userId . '_notification_count', -1);
        }

        return $this->resNoContent();
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
        $userId = $this->getAuthUserId();

        $ids = Notifications
            ::where('to_user_id', $userId)
            ->pluck('id')
            ->toArray();

        if (!$ids)
        {
            return $this->resNoContent();
        }

        Notifications
            ::where('to_user_id', $userId)
            ->update([
                'checked' => true
            ]);

        Redis::pipeline(function ($pipe) use ($ids, $userId)
        {
            foreach ($ids as $id)
            {
                $pipe->DEL('notification-' . $id);
            }

            Redis::DEL('user-' . $userId . '-notification-ids');
            Redis::SET('user_' . $userId . '_notification_count', 0);
        });

        return $this->resNoContent();
    }

    /**
     * 用户交易记录列表
     *
     * @Get("/user/transactions")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Request({"min_id": "看过的最小id", "default": 0, "required": true}),
     *      @Request({"take": "条数", "default": 15}),
     *      @Response(200, body={"code": 0, "data": "消息列表"}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户"})
     * })
     */
    public function transactions(Request $request)
    {
        $minId = $request->get('min_id') ?: 0;
        $take = $request->get('take') ?: 15;
        $userId = $this->getAuthUserId();

        $list = DB::table('user_coin')
            ->where('user_id', $userId)
            ->orWhere('from_user_id', $userId)
            ->when($minId, function ($query) use ($minId)
            {
                return $query->where('id', '<', $minId);
            })
            ->select('id', 'created_at', 'from_user_id', 'user_id', 'type', 'type_id', 'id', 'count')
            ->orderBy('id', 'DESC')
            ->take($take)
            ->get();

        $result = [];
        foreach ($list as $item)
        {
            $actionId = (int)$item->type_id;

            $transaction = [
                'id' => (int)$item->id,
                'action_type' => (int)$item->type,
                'type' => 0, // 0 是支出，1是收入
                'action' => '',
                'count' => (int)$item->count, // 金额
                'about' => [
                    'id' => $actionId
                ],
                'created_at' => $item->created_at, // 创建时间
            ];

            if ($item->type == 0)
            {
                $transaction['type'] = 1;
                $transaction['action'] = '每日签到（旧）';
            }
            else if ($item->type == 1)
            {
                $transaction['action'] = '打赏帖子';
                if ($item->from_user_id == $userId)
                {
                    $transaction['type'] = 0;
                }
                else
                {
                    $transaction['type'] = 1;
                }

                $postRepository = new PostRepository();
                $post = $postRepository->item($actionId);
                $transaction['about']['title'] = $post['title'];
            }
            else if ($item->type == 2)
            {
                $transaction['action'] = '邀请注册';
                $transaction['type'] = 1;

                $userRepository = new UserRepository();
                $user = $userRepository->item($actionId);
                $transaction['about']['nickname'] = $user['nickname'];
                $transaction['about']['zone'] = $user['zone'];
            }
            else if ($item->type == 3)
            {
                $transaction['action'] = '偶像应援';
                $transaction['type'] = 0;

                $cartoonRoleRepository = new CartoonRoleRepository();
                $role = $cartoonRoleRepository->item($actionId);
                $transaction['about']['name'] = $role['name'];
            }
            else if ($item->type == 4)
            {
                $transaction['action'] = '打赏图片';
                if ($item->from_user_id == $userId)
                {
                    $transaction['type'] = 0;
                }
                else
                {
                    $transaction['type'] = 1;
                }

                $imageRepository = new ImageRepository();
                $image = $imageRepository->item($actionId);
                $transaction['about']['title'] = $image['name'];
            }
            else if ($item->type == 5)
            {
                $transaction['action'] = '提现';
                $transaction['type'] = 0;
            }
            else if ($item->type == 6)
            {
                $transaction['action'] = '打赏漫评';
                if ($item->from_user_id == $userId)
                {
                    $transaction['type'] = 0;
                }
                else
                {
                    $transaction['type'] = 1;
                }

                $scoreRepository = new ScoreRepository();
                $score = $scoreRepository->item($actionId);
                $transaction['about']['title'] = $score['title'];
            }
            else if ($item->type == 7)
            {
                $transaction['action'] = '打赏回答';
                if ($item->from_user_id == $userId)
                {
                    $transaction['type'] = 0;
                }
                else
                {
                    $transaction['type'] = 1;
                }

                $answerRepository = new AnswerRepository();
                $answer = $answerRepository->item($actionId);
                $transaction['about']['intro'] = $answer['intro'];
            }
            else if ($item->type == 8)
            {
                $transaction['type'] = 1;
                $transaction['action'] = '每日签到';
            }
            else if ($item->type == 9)
            {
                $transaction['type'] = 0;
                $transaction['action'] = '删除帖子';

                $postRepository = new PostRepository();
                $post = $postRepository->item($actionId, true);
                $transaction['about']['title'] = $post['title'];
            }
            else if ($item->type == 10)
            {
                $transaction['type'] = 0;
                $transaction['action'] = '删除图片';

                $imageRepository = new ImageRepository();
                $image = $imageRepository->item($actionId, true);
                $transaction['about']['title'] = $image['name'];
            }
            else if ($item->type == 11)
            {
                $transaction['type'] = 0;
                $transaction['action'] = '删除漫评';

                $scoreRepository = new ScoreRepository();
                $score = $scoreRepository->item($actionId, true);
                $transaction['about']['title'] = $score['title'];
            }
            else if ($item->type == 12)
            {
                $transaction['type'] = 0;
                $transaction['action'] = '删除回答';


                $answerRepository = new AnswerRepository();
                $answer = $answerRepository->item($actionId, true);
                $transaction['about']['intro'] = $answer['intro'];
            }
            else if ($item->type == 13)
            {
                $transaction['type'] = 0;
                $transaction['action'] = '打赏视频';

                $videoRepository = new VideoRepository();
                $video = $videoRepository->item($actionId);
                $transaction['about']['title'] = $video['name'];
            }
            else if ($item->type == 14)
            {
                $transaction['type'] = 0;
                $transaction['action'] = '删除视频';

                $videoRepository = new VideoRepository();
                $video = $videoRepository->item($actionId, true);
                $transaction['about']['title'] = $video['name'];
            }
            else if ($item->type == 15)
            {
                $transaction['type'] = 1;
                $transaction['action'] = '活跃奖励';

                $transaction['about']['title'] = '你昨天的战斗力超过了100，赠送一个团子~';
            }
            else if ($item->type == 16)
            {
                $transaction['type'] = 1;
                $transaction['action'] = '活跃奖励';

                $transaction['about']['title'] = '活跃版主每天赠送一个团子，请查收~';
            }

            $result[] = $transaction;
        }

        return $this->resOK($result);
    }

    // 用户邀请注册的列表
    public function userInviteList(Request $request)
    {
        $id = $request->get('id');

        $userRepository = new UserRepository();
        $user = $userRepository->item($id);
        if (is_null($user))
        {
            return $this->resErrNotFound();
        }

        $ids = UserCoin
            ::where('user_id', $id)
            ->where('type', 2)
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

    // 获取推荐用户
    public function recommendedUsers()
    {
        $userRepository = new UserRepository();

        $ids = $userRepository->Cache('recommended-activity-user-ids', function () use ($userRepository)
        {
            $ids = UserCoin
                ::whereIn('type', [1, 4, 6, 7])
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
            return [];
        }

        $result = [];
        foreach ($ids as $item)
        {
            $user = $userRepository->item($item['id']);
            if (is_null($user))
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
        if ($type === 'ip_address')
        {
            $userIds = $userIpAddress->addressUsers($value);

            return $this->resOK($userIds);
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

        $userRepository = new UserRepository();
        $user = $userRepository->item($userId, true);
        if (is_null($user))
        {
            return $this->resOK(null);
        }
        $userLevel = new UserLevel();
        $userActivityService = new UserActivity();

        $user['coin_count'] = User::where('id', $userId)->pluck('coin_count')->first();
        $user['coin_from_sign'] = $userRepository->userSignCoin($userId);
        $user['ip_address'] = $userIpAddress->userIps($userId);
        $user['level'] = $userLevel->convertExpToLevel($user['exp']);
        $user['power'] = $userActivityService->get($userId);

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

        $list = DB::table('user_coin')
            ->where('user_id', $userId)
            ->orWhere('from_user_id', $userId)
            ->select('id', 'created_at', 'from_user_id', 'user_id', 'type', 'type_id', 'id', 'count')
            ->orderBy('created_at', 'DESC')
            ->take(($toPage - $curPage) * $take)
            ->skip($curPage * $take)
            ->get();

        $userRepository = new UserRepository();
        $result = [];
        foreach ($list as $item)
        {
            $transaction = [
                'id' => $item->id,
                'type' => '',
                'action' => '',
                'count' => $item->count,
                'action_id' => $item->type_id,
                'created_at' => $item->created_at,
                'about_user_id' => '无',
                'about_user_phone' => '无',
                'about_user_sign_at' => '无'
            ];
            if ($item->type == 0)
            {
                $transaction['type'] = '收入';
                $transaction['action'] = '每日签到（旧）';
            }
            else if ($item->type == 1)
            {
                $transaction['action'] = '打赏帖子';
                if ($item->from_user_id == $userId)
                {
                    $transaction['type'] = '支出';
                }
                else
                {
                    $transaction['type'] = '收入';
                }
            }
            else if ($item->type == 2)
            {
                $transaction['action'] = '邀请注册';
                $transaction['type'] = '收入';
            }
            else if ($item->type == 3)
            {
                $transaction['action'] = '偶像应援';
                $transaction['type'] = '支出';
            }
            else if ($item->type == 4)
            {
                $transaction['action'] = '打赏图片';
                if ($item->from_user_id == $userId)
                {
                    $transaction['type'] = '支出';
                }
                else
                {
                    $transaction['type'] = '收入';
                }
            }
            else if ($item->type == 5)
            {
                $transaction['action'] = '提现';
                $transaction['type'] = '支出';
            }
            else if ($item->type == 6)
            {
                $transaction['action'] = '打赏漫评';
                if ($item->from_user_id == $userId)
                {
                    $transaction['type'] = '支出';
                }
                else
                {
                    $transaction['type'] = '收入';
                }
            }
            else if ($item->type == 7)
            {
                $transaction['action'] = '打赏回答';
                if ($item->from_user_id == $userId)
                {
                    $transaction['type'] = '支出';
                }
                else
                {
                    $transaction['type'] = '收入';
                }
            }
            else if ($item->type == 8)
            {
                $transaction['type'] = '收入';
                $transaction['action'] = '每日签到';
            }
            else if ($item->type == 9)
            {
                $transaction['type'] = '支出';
                $transaction['action'] = '删除帖子';
            }
            else if ($item->type == 10)
            {
                $transaction['type'] = '支出';
                $transaction['action'] = '删除图片';
            }
            else if ($item->type == 11)
            {
                $transaction['type'] = '支出';
                $transaction['action'] = '删除漫评';
            }
            else if ($item->type == 12)
            {
                $transaction['type'] = '支出';
                $transaction['action'] = '删除回答';
            }
            else if ($item->type == 13)
            {
                $transaction['type'] = '支出';
                $transaction['action'] = '打赏视频';
            }
            else if ($item->type == 14)
            {
                $transaction['type'] = '支出';
                $transaction['action'] = '删除视频';
            }
            else if ($item->type == 15)
            {
                $transaction['type'] = '收入';
                $transaction['action'] = '普通用户100战斗力送团子';
            }
            else if ($item->type == 16)
            {
                $transaction['type'] = '收入';
                $transaction['action'] = '番剧管理者100战斗力送团子';
            }

            if ($transaction['type'] === '收入' && $item->from_user_id != 0 && $item->from_user_id != $userId)
            {
                $user = $userRepository->item($item->from_user_id);
                $transaction['about_user_id'] = $user['id'];
                $transaction['about_user_phone'] = $user['phone'];
                $transaction['about_user_sign_at'] = $user['created_at'];
            }
            if ($transaction['type'] === '支出' && $item->user_id != 0)
            {
                $user = $userRepository->item($item->user_id);
                $transaction['about_user_id'] = $user['id'];
                $transaction['about_user_phone'] = $user['phone'];
                $transaction['about_user_sign_at'] = $user['created_at'];
            }

            $result[] = $transaction;
        }

        return $this->resOK([
            'list' => $result,
            'total' => UserCoin::where('user_id', $userId)->orWhere('from_user_id', $userId)->count()
        ]);
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
        $coinCount = User::where('id', $userId)
            ->pluck('coin_count')
            ->first();

        $coinCount = $coinCount - UserCoin::whereRaw('user_id = ? and type = ?', [$userId, 8])->count();

        if ($coinCount < 100)
        {
            return $this->resErrBad('未满100团子');
        }

        $money = $request->get('money');
        if ($money > $coinCount)
        {
            return $this->resErrBad('超出拥有金额');
        }

        User::where('id', $userId)->increment('coin_count', -$money);
        UserCoin::create([
            'from_user_id' => 0,
            'user_id' => $userId,
            'type' => 5,
            'count' => $money
        ]);

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
