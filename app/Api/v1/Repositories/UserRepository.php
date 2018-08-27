<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Api\V1\Repositories;

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
use App\Models\User;
use App\Models\UserCoin;
use App\Models\UserSign;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        return UserSign::whereRaw('user_id = ? and created_at > ?', [$userId, Carbon::now()->startOfDay()])->count() !== 0;
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

    public function toggleCoin($isDelete, $fromUserId, $toUserId, $type, $type_id)
    {
        if (intval($fromUserId) === intval($toUserId))
        {
            return false;
        }

        if ($isDelete)
        {
            $log = UserCoin::whereRaw('from_user_id = ? and user_id = ? and type = ? and type_id = ?', [$fromUserId, $toUserId, $type, $type_id])->first();
            if (is_null($log))
            {
                return false;
            }

            $log->delete();
        }
        else
        {
            if ($type !== 2)
            {
                $count = User::where('id', $fromUserId)->pluck('coin_count')->first();

                if ($count <= 0)
                {
                    return false;
                }
            }

            UserCoin::create([
                'user_id' => $toUserId,
                'from_user_id' => $fromUserId,
                'type' => $type,
                'type_id' => $type_id
            ]);

            if ($toUserId)
            {
                User::where('id', $toUserId)->increment('coin_count', 1);
            }

            if ($type !== 2)
            {
                User::where('id', $fromUserId)->increment('coin_count', -1);
            }
        }

        return true;
    }

    public function getNotificationCount($userId)
    {
        return Notifications::whereRaw('to_user_id = ? and checked = ?', [$userId, false])->count();
    }

    public function getNotifications($userId, $minId, $take)
    {
        $list = $this->RedisList('user-' . $userId . '-notification-ids', function () use ($userId)
        {
            return Notifications
                ::where('to_user_id', $userId)
                ->orderBy('id', 'DESC')
                ->pluck('id');
        });

        if (empty($list))
        {
            return [
                'list' => [],
                'noMore' => true,
                'total' => 0
            ];
        }

        $idsObj = $this->filterIdsByMaxId($list, $minId, $take);
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
        foreach ($notifies as $item)
        {
            $notify = $this->Cache('notification-' . $item['id'], function () use ($item)
            {
                $type = $item['type'];
                $link = $this->computeNotificationLink($type, $item['model_id'], $item['comment_id'], $item['reply_id']);
                if (!$link)
                {
                    return null;
                }
                $template = $this->computeNotificationMessage($type);

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
                    $repository = $this->computeNotificationRepository($type);
                    $model = $repository->item($item['model_id']);
                    $model = $this->convertModel($model, $type);
                    $title = $this->computeNotificationMessageTitle($model);
                    $template = str_replace('${title}', '<a class="title" href="'. $link .'">' . $title . '</a>', $template);

                    $notification['model'] = [
                        'id' => $model['id'],
                        'title' => $title
                    ];
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
            'noMore' => $idsObj['noMore'],
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

    protected function computeNotificationLink($type, $modalId, $commentId = 0, $replyId = 0)
    {
        switch ($type)
        {
            case 0:
                return '';
                break;
            case 1:
                return '/post/' . $modalId;
                break;
            case 2:
                return '/post/' . $modalId;
                break;
            case 3:
                return '/post/' . $modalId;
                break;
            case 4:
                return '/post/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 5:
                return '/post/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 6:
                return '/pins/' . $modalId;
                break;
            case 7:
                return '/pins/' . $modalId;
                break;
            case 8:
                return '/pins/' . $modalId;
                break;
            case 9:
                return '/pins/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 10:
                return '/pins/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 11:
                return '/review/' . $modalId;
                break;
            case 12:
                return '/review/' . $modalId;
                break;
            case 13:
                return '/review/' . $modalId;
                break;
            case 14:
                return '/review/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 15:
                return '/review/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 16:
                return '/video/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 17:
                return '/video/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 18:
                return '/post/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 19:
                return '/post/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 20:
                return '/pins/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 21:
                return '/pins/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 22:
                return '/review/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 23:
                return '/review/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 24:
                return '/video/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 25:
                return '/video/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 26:
                return '/qaq/' . $modalId;
                break;
            case 27:
                return '/qaq/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 28:
                return '/qaq/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 29:
                return '/qaq/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 30:
                return '/qaq/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 31:
                return '/soga/' . $modalId;
                break;
            case 32:
                return '/soga/' . $modalId;
                break;
            case 33:
                return '/soga/' . $modalId;
                break;
            case 34:
                return '/soga/' . $modalId;
                break;
            case 35:
                return '/soga/' . $modalId;
                break;
            case 36:
                return '/soga/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 37:
                return '/soga/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            case 38:
                return '/soga/' . $modalId . '?comment-id=' . $commentId;
                break;
            case 39:
                return '/soga/' . $modalId . '?comment-id=' . $commentId . '&reply-id=' . $replyId;
                break;
            default:
                return '';
                break;
        }
    }

    protected function computeNotificationMessage($type)
    {
        switch ($type)
        {
            case 0:
                return '';
                break;
            case 1:
                return '${user}喜欢了你的帖子${title}';
                break;
            case 2:
                return '${user}打赏了你的帖子${title}';
                break;
            case 3:
                return '${user}收藏了你的帖子${title}';
                break;
            case 4:
                return '${user}评论了你的帖子${title}';
                break;
            case 5:
                return '${user}回复了你在的帖子${title}下的评论';
                break;
            case 6:
                return '${user}喜欢了你的图片${title}';
                break;
            case 7:
                return '${user}打赏了你的图片${title}';
                break;
            case 8:
                return '${user}收藏了你的图片${title}';
                break;
            case 9:
                return '${user}评论了你的图片${title}';
                break;
            case 10:
                return '${user}回复了你在的图片${title}下的评论';
                break;
            case 11:
                return '${user}喜欢了你的漫评${title}';
                break;
            case 12:
                return '${user}打赏了你的漫评${title}';
                break;
            case 13:
                return '${user}收藏了你的漫评${title}';
                break;
            case 14:
                return '${user}评论了你的漫评${title}';
                break;
            case 15:
                return '${user}回复了你在的漫评${title}下的评论';
                break;
            case 16:
                return '${user}评论了你的视频${title}';
                break;
            case 17:
                return '${user}回复了你在的视频${title}下的评论';
                break;
            case 18:
                return '${user}赞了你在的帖子${title}下的评论';
                break;
            case 19:
                return '${user}赞了你在的帖子${title}下的回复';
                break;
            case 20:
                return '${user}赞了你在的图片${title}下的评论';
                break;
            case 21:
                return '${user}赞了你在的图片${title}下的回复';
                break;
            case 22:
                return '${user}赞了你在的评分${title}下的评论';
                break;
            case 23:
                return '${user}赞了你在的评分${title}下的回复';
                break;
            case 24:
                return '${user}赞了你在的视频${title}下的评论';
                break;
            case 25:
                return '${user}赞了你在的视频${title}下的回复';
                break;
            case 26:
                return '${user}关注了你提的问题${title}';
                break;
            case 27:
                return '${user}评论了你提的问题${title}';
                break;
            case 28:
                return '${user}赞了你在问题${title}下的评论';
                break;
            case 29:
                return '${user}回复了你在问题${title}下的评论';
                break;
            case 30:
                return '${user}赞了你在问题${title}下的回复';
                break;
            case 31:
                return '${user}回答了你的问题${title}';
                break;
            case 32:
                return '${user}赞同了你在问题${title}下的回答';
                break;
            case 33:
                return '${user}喜欢了你在问题${title}下的回答';
                break;
            case 34:
                return '${user}打赏了你在问题${title}下的回答';
                break;
            case 35:
                return '${user}收藏了你在问题${title}下的回答';
                break;
            case 36:
                return '${user}评论了你在问题${title}下的回答';
                break;
            case 37:
                return '${user}回复了你在问题${title}下的评论';
                break;
            case 38:
                return '${user}赞了你在问题${title}下的评论';
                break;
            case 39:
                return '${user}赞了你在问题${title}下的回复';
                break;
            default:
                return '';
                break;
        }
    }

    protected function computeNotificationRepository($type)
    {
        switch ($type)
        {
            case 0:
                return null;
                break;
            case 1:
                return new PostRepository();
                break;
            case 2:
                return new PostRepository();
                break;
            case 3:
                return new PostRepository();
                break;
            case 4:
                return new PostRepository();
                break;
            case 5:
                return new PostRepository();
                break;
            case 6:
                return new ImageRepository();
                break;
            case 7:
                return new ImageRepository();
                break;
            case 8:
                return new ImageRepository();
                break;
            case 9:
                return new ImageRepository();
                break;
            case 10:
                return new ImageRepository();
                break;
            case 11:
                return new ScoreRepository();
                break;
            case 12:
                return new ScoreRepository();
                break;
            case 13:
                return new ScoreRepository();
                break;
            case 14:
                return new ScoreRepository();
                break;
            case 15:
                return new ScoreRepository();
                break;
            case 16:
                return new VideoRepository();
                break;
            case 17:
                return new VideoRepository();
                break;
            case 18:
                return new PostRepository();
                break;
            case 19:
                return new PostRepository();
                break;
            case 20:
                return new ImageRepository();
                break;
            case 21:
                return new ImageRepository();
                break;
            case 22:
                return new ScoreRepository();
                break;
            case 23:
                return new ScoreRepository();
                break;
            case 24:
                return new VideoRepository();
                break;
            case 25:
                return new VideoRepository();
                break;
            case 26:
                return new QuestionRepository();
                break;
            case 27:
                return new QuestionRepository();
                break;
            case 28:
                return new QuestionRepository();
                break;
            case 29:
                return new QuestionRepository();
                break;
            case 30:
                return new QuestionRepository();
                break;
            case 31:
                return new AnswerRepository();
                break;
            case 32:
                return new AnswerRepository();
                break;
            case 33:
                return new AnswerRepository();
                break;
            case 34:
                return new AnswerRepository();
                break;
            case 35:
                return new AnswerRepository();
                break;
            case 36:
                return new AnswerRepository();
                break;
            case 37:
                return new AnswerRepository();
                break;
            case 38:
                return new AnswerRepository();
                break;
            case 39:
                return new AnswerRepository();
                break;
            default:
                return null;
                break;
        }
    }

    protected function computeNotificationMessageTitle($model)
    {
        if (isset($model['title']))
        {
            return $model['title'];
        }

        if (isset($model['name']))
        {
            return $model['name'];
        }

        if (isset($model['nickname']))
        {
            return $model['nickname'];
        }

        if (isset($model['intro']))
        {
            return $model['intro'];
        }

        return '';
    }

    protected function convertModel($model, $type)
    {
        if (isset($model['question_id']))
        {
            $questionRepository = new QuestionRepository();
            return $questionRepository->item($model['question_id']);
        }

        return $model;
    }
}