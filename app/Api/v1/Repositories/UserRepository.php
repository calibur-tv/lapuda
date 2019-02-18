<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Api\V1\Repositories;

use App\Api\V1\Presenter\NotificationPresenter;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Models\DayStats;
use App\Models\Bangumi;
use App\Models\CartoonRole;
use App\Models\Image;
use App\Models\Notifications;
use App\Models\Post;
use App\Models\SystemNotice;
use App\Models\User;
use App\Models\UserSign;
use App\Models\Video;
use Carbon\Carbon;
use App\Services\Qiniu\Http\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UserRepository extends Repository
{
    public function item($id, $isShow = false)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->RedisHash('user_'.$id, function () use ($id)
        {
            $user = User
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($user))
            {
                return null;
            }
            $user = $user->toArray();
            $user['sex'] = $this->maskSex($user['sex']);

            return $user;
        });

        if (!$result)
        {
            return null;
        }

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
        }

        return $result;
    }

    public function maskSex($sex)
    {
        switch ($sex)
        {
            case 0:
                $res = '未知';
                break;
            case 1:
                $res = '男';
                break;
            case 2:
                $res = '女';
                break;
            case 3:
                $res = '伪娘';
                break;
            case 4:
                $res = '药娘';
                break;
            case 5:
                $res = '扶她';
                break;
            default:
                $res = '未知';
        }

        return $res;
    }

    public function daySigned($userId)
    {
        return $this->RedisItem('user_' . $userId . '_day_signed_' . date('y-m-d', time()), function () use ($userId)
        {
            return UserSign::whereRaw('user_id = ? and created_at > ?', [$userId, Carbon::now()->startOfDay()])->count() !== 0;
        });
    }

    public function replyPostIds($userId)
    {
        $postCommentService = new PostCommentService();

        return $postCommentService->getUserCommentIds($userId);
    }

    public function replyPostItem($userId, $postId)
    {
        return $this->Cache('user_'.$userId.'_reply_post_'.$postId, function () use ($postId)
        {
            $postCommentService = new PostCommentService();
            $reply = $postCommentService->getMainCommentItem($postId);

            $postRepository = new PostRepository();
            $post = $postRepository->item($reply['modal_id']);
            if (is_null($post))
            {
                return null;
            }

            $bangumiRepository = new BangumiRepository();
            $bangumi = $bangumiRepository->item($post['bangumi_id']);
            if (is_null($bangumi))
            {
                return null;
            }

            $reply['bangumi'] = $bangumi;
            $reply['post'] = $post;

            $postTransformer = new PostTransformer();
            return $postTransformer->userReply($reply);

        }, 'm');
    }

    public function getNotificationCount($userId)
    {
        return (int)$this->RedisItem('user_' . $userId . '_notification_count', function () use ($userId)
        {
            return Notifications::whereRaw('to_user_id = ? and checked = ?', [$userId, false])->count();
        });
    }

    public function getNotifications($userId, $minId, $take)
    {
        $ids = $this->RedisList('user-' . $userId . '-notification-ids', function () use ($userId)
        {
            return Notifications
                ::where('to_user_id', $userId)
                ->orderBy('id', 'DESC')
                ->pluck('id');
        });

        if (empty($ids))
        {
            return [
                'list' => [],
                'noMore' => true,
                'total' => 0
            ];
        }

        $idsObj = $this->filterIdsByMaxId($ids, $minId, $take);
        if (empty($idsObj['ids']))
        {
            return [
                'list' => [],
                'noMore' => true,
                'total' => 0
            ];
        }

        $notifies = Notifications
            ::whereIn('id', $idsObj['ids'])
            ->orderBy('id', 'DESC')
            ->get()
            ->toArray();

        $result = [];
        $notificationPresenter = new NotificationPresenter();
        foreach ($notifies as $item)
        {
            $notify = $this->Cache('notification-' . $item['id'], function () use ($item, $notificationPresenter)
            {
                $type = (int)$item['type'];
                $link = $notificationPresenter->computeNotificationLink($type, $item['model_id'], $item['comment_id'], $item['reply_id']);
                $isSystemNotice = $type === 42 || $type === 43;
                if ($isSystemNotice)
                {
                    // APP 消息通知崩溃了，暂时用站娘账号
                    $item['from_user_id'] = 2;
                }
                $template = $notificationPresenter->computeNotificationMessage($type);

                $notification = [
                    'id' => (int)$item['id'],
                    'checked' => (boolean)$item['checked'],
                    'type' => $type,
                    'user' => null,
                    'message' => '',
                    'model' => '',
                    'created_at' => $item['created_at']
                ];

                if ($item['from_user_id'])
                {
                    $user = $this->item($item['from_user_id']);
                    if (is_null($user))
                    {
                        return null;
                    }

                    $template = str_replace('${user}', '<a class="user" href="/user/'. $user['zone'] .'">' . $user['nickname'] . '</a>', $template);

                    $notification['user'] = [
                        'id' => $user['id'],
                        'zone' => $user['zone'],
                        'avatar' => $user['avatar'],
                        'nickname' => $user['nickname']
                    ];
                }

                if ($item['model_id'])
                {
                    $repository = $notificationPresenter->computeNotificationRepository($type);
                    $model = $repository->item($item['model_id']);
                    if (is_null($model))
                    {
                        return null;
                    }

                    $model = $notificationPresenter->convertModel($model, $type);
                    $title = $notificationPresenter->computeNotificationMessageTitle($model);
                    $template = str_replace('${title}', '<a class="title" href="'. $link .'">' . $title . '</a>', $template);

                    $notification['model'] = [
                        'id' => $model['id'],
                        'title' => $title
                    ];
                }

                if ($isSystemNotice)
                {
                    $notification['model'] = [
                        'id' => 4929,
                        'title' => '系统消息'
                    ];
                    $template = str_replace('${title}', '<a class="title" href="'. $link .'">点击查看详情</a>', $template);
                }

                $notification['message'] = $template;
                $notification['link'] = $link;

                return $notification;
            }, 'm');

            if ($notify)
            {
                $result[] = $notify;
            }
        }

        return [
            'list' => $result,
            'noMore' => empty($result) ? true : $idsObj['noMore'],
            'total' => $idsObj['total']
        ];
    }

    public function followedBangumis($userId, $page = -1, $count = 10)
    {
        $bangumiFollowService = new BangumiFollowService();
        $idsObj = $bangumiFollowService->usersDoIds($userId, $page, $count);
        $bangumiIds = $idsObj['ids'];
        if (empty($bangumiIds))
        {
            return [];
        }

        $bangumiRepository = new BangumiRepository();
        $bangumis = [];
        foreach ($bangumiIds as $id => $time)
        {
            $bangumi = $bangumiRepository->item($id);
            if (is_null($bangumi))
            {
                continue;
            }
            $bangumi['created_at'] = $time;
            $bangumis[] = $bangumi;
        }

        $bangumiTransformer = new BangumiTransformer();

        return $bangumiTransformer->userFollowedList($bangumis);
    }

    public function appendUserToList($list)
    {
        $result = [];
        foreach ($list as $item)
        {
            $user = $this->item($item['user_id']);
            if (is_null($user))
            {
                continue;
            }
            $item['user'] = $user;
            $result[] = $item;
        }

        return $result;
    }

    public function statsByDate($nowTime)
    {
        $today = strtotime(date('Y-m-d', $nowTime));
        $createdAt = date('Y-m-d H:m:s', $today);
        $yesterday = $today - 86400;
        // user
        $userCount = User::where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('user_register', $yesterday, $userCount);
        // post
        $postCount = Post::where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('create_post', $yesterday, $postCount);
        // 评论数
        $commentCount = 0;
        $commentCount += DB::table('post_comments')->where('created_at', '<', $createdAt)
            ->count();
        $commentCount += DB::table('video_comments')->where('created_at', '<', $createdAt)
            ->count();
        $commentCount += DB::table('image_comments')->where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('create_comment', $yesterday, $commentCount);
        // imageCount
        $imageCount = Image::where('is_album', 0)
            ->where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('create_image', $yesterday, $imageCount);
        // album_count
        $albumCount = Image::where('created_at', '<', $createdAt)
            ->where('is_album', 1)
            ->count();
        $this->setDayStats('create_image_album', $yesterday, $albumCount);
        // bangumiCount
        $bangumiCount = Bangumi::where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('create_bangumi', $yesterday, $bangumiCount);
        // videoCount
        $videoCount = Video::where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('create_video', $yesterday, $videoCount);
        // roleCount
        $roleCount = CartoonRole::where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('create_role', $yesterday, $roleCount);
    }

    public function getCoinIdByType($type)
    {
        switch ($type)
        {
            case 'sign':
                return 0;
                break;
            case 'post':
                return 1;
                break;
            case 'invite':
                return 2;
                break;
            case 'role':
                return 3;
                break;
            case 'image':
                return 4;
                break;
            case 'withdrawal':
                return 5;
                break;
            case 'score':
                return 6;
                break;
            case 'answer':
                return 7;
                break;
            case 'video':
                return 13;
                break;
            default:
                return -1;
        }
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $user = $this->item($id);
        $content = $user['nickname'] . ',' . $user['zone'];

        $job = (new \App\Jobs\Search\Index($type, 'user', $id, $content));
        dispatch($job);
    }

    protected function setDayStats($type, $day, $count)
    {
        if (!DayStats::whereRaw('type = ? and day = ?', [$type, $day])->count())
        {
            DayStats::insert([
                'type' => $type,
                'day' => $day,
                'count' => $count
            ]);
        }
    }

    public function computedSystemNoticeCount($lastReadId)
    {
        $noticeTimeList = $this->Cache('system_notice_id_list', function ()
        {
            return SystemNotice
                ::orderBy('id', 'DESC')
                ->pluck('id')
                ->toArray();
        });

        if (!$lastReadId)
        {
            return count($noticeTimeList);
        }

        $result = 0;
        if ($noticeTimeList)
        {
            foreach ($noticeTimeList as $id)
            {
                if ($id > $lastReadId)
                {
                    $result++;
                }
                else
                {
                    break;
                }
            }
        }

        return $result;
    }

    public function getWechatAccessToken()
    {
        return $this->RedisItem('wechat_js_sdk_access_token', function ()
        {
            $client = new Client();
            $appId = config('services.weixin.client_id');
            $appSecret = config('services.weixin.client_secret');
            $resp = $client->get(
                "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$appSecret}",
                [
                    'Accept' => 'application/json'
                ]
            );

            try
            {
                $body = $resp->body;
                $token = json_decode($body, true)['access_token'];
            }
            catch (\Exception $e)
            {
                $token = '';
            }

            return $token;
        });
    }
}