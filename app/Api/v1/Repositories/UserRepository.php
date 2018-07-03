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
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\DayStats;
use App\Models\Bangumi;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use App\Models\Image;
use App\Models\Notifications;
use App\Models\Post;
use App\Models\PostImages;
use App\Models\PostMark;
use App\Models\User;
use App\Models\UserCoin;
use App\Models\UserSign;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserRepository extends Repository
{
    public function item($id, $force = false)
    {
        if (!$id)
        {
            return null;
        }

        if ($force)
        {
            return User::withTrashed()->find($id);
        }

        return $this->RedisHash('user_'.$id, function () use ($id)
        {
            $user = User::where('id', $id)->first();
            if (is_null($user))
            {
                return null;
            }
            $user = $user->toArray();
            $user['sex'] = $this->maskSex($user['sex']);

            return $user;
        });
    }

    public function getUserIdByZone($zone, $force = false)
    {
        $userId = User::where('zone', $zone)
            ->when($force, function ($query)
            {
                return $query->withTrashed();
            })
            ->pluck('id')
            ->first();

        return is_null($userId) ? 0 : $userId;
    }

    public function list($ids)
    {
        if (empty($ids))
        {
            return [];
        }
        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->item($id);
            if ($item) {
                $result[] = $item;
            }
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
            case 3:     // 男,保密
                $res = '保密';
                break;
            case 4:     // 女,保密
                $res = '保密';
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

    public function minePostIds($userId)
    {
        return $this->RedisList('user_'.$userId.'_minePostIds', function () use ($userId)
        {
           return Post::where('user_id', $userId)
               ->orderBy('created_at', 'DESC')
               ->pluck('id');
        });
    }

    public function replyPostIds($userId)
    {
        $postCommentService = new PostCommentService();

        return $postCommentService->getUserCommentIds($userId);
    }

    public function likedPostIds($userId)
    {
        $postLikeService = new PostLikeService();

        return $postLikeService->usersDoIds($userId, -1);
    }

    public function likedPost($userId)
    {
        $postLikeService = new PostLikeService();
        $ids = $postLikeService->usersDoIds($userId, -1);

        if (empty($ids))
        {
            return [];
        }

        $postRepository = new PostRepository();
        $posts = [];

        foreach ($ids as $id => $time)
        {
            $post = $postRepository->item($id);
            if(is_null($post) || !$post['title'])
            {
                continue;
            }
            $post['created_at'] = $time;
            $posts[] = $post;
        }

        $postTransformer = new PostTransformer();
        return [
            'list' => $postTransformer->userLike($posts),
            'total' => count($ids),
            'noMore' => true
        ];
    }

    public function markedPost($userId)
    {
        $postMarkService = new PostMarkService();
        $ids = $postMarkService->usersDoIds($userId, -1);

        if (empty($ids))
        {
            return [];
        }

        $postRepository = new PostRepository();
        $posts = [];

        foreach ($ids as $id => $time)
        {
            $post = $postRepository->item($id);
            if(is_null($post))
            {
                continue;
            }
            $post['created_at'] = $time;
            $posts[] = $post;
        }

        $postTransformer = new PostTransformer();
        return [
            'list' => $postTransformer->userMark($posts),
            'total' => count($ids),
            'noMore' => true
        ];
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

                if ($count <= 0) {
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
        $list = Notifications::where('to_user_id', $userId)
            ->when($minId, function ($query) use ($minId) {
                return $query->where('id', '<', $minId);
            })
            ->orderBy('id', 'DESC')
            ->take($take)
            ->get()
            ->toArray();

        if (empty($list))
        {
            return [];
        }

        $userRepository = new UserRepository();
        $postRepository = new PostRepository();
        $transformer = new UserTransformer();

        $result = [];

        foreach ($list as $item)
        {
            $type = intval($item['type']);
            $user = $userRepository->item($item['from_user_id']);
            $ids = explode(',', $item['about_id']);

            if ($type === 1)
            {
                $postId = $ids[1];
                $post = $postRepository->item($postId);
                if (is_null($post))
                {
                    continue;
                }
                $about = [
                    'resource' => $item['about_id'],
                    'title' => $post['title'],
                    'link' => '/post/'.$postId . '?reply=' . $ids[0],
                ];
                $model = 'post';
            }
            else if ($type === 2)
            {
                $commentId = $ids[0];
                $replyId = $ids[1];
                $postId = $ids[2];
                $post = $postRepository->item($postId);
                if (is_null($post))
                {
                    continue;
                }
                $about = [
                    'resource' => $item['about_id'],
                    'title' => $post['title'],
                    'link' => '/post/'.$postId.'?reply='.$replyId.'&comment='.$commentId,
                ];
                $model = 'post';
            }
            else if ($type === 3)
            {
                $post = $postRepository->item($item['about_id']);
                if (is_null($post))
                {
                    continue;
                }
                $about = [
                    'resource' => $item['about_id'],
                    'title' => $post['title'],
                    'link' => '/post/'.$ids[0]
                ];
                $model = 'post';
            }
            else if ($type === 4)
            {
                $post = $postRepository->item($ids[1]);
                if (is_null($post))
                {
                    continue;
                }
                $about = [
                    'resource' => $item['about_id'],
                    'title' => $post['title'],
                    'link' => '/post/'.$ids[1].'?reply='.$ids[0]
                ];
                $model = 'post';
            }
            else if ($type === 5)
            {
                $model = 'image';
                $about = [
                    'resource' => '',
                    'title' => '',
                    'link' => ''
                ];
            }

            $result[] = [
                'id' => $item['id'],
                'model' => $model,
                'type' => $type,
                'user' => $user,
                'data' => $about,
                'checked' => $item['checked']
            ];
        }

        return $transformer->notification($result);
    }

    public function rolesIds($userId)
    {
        return $this->RedisList('user_'.$userId.'_followRoleIds', function () use ($userId)
        {
            return CartoonRoleFans::where('user_id', $userId)
                ->orderBy('updated_at', 'DESC')
                ->pluck('role_id');
        });
    }

    public function imageAlbums($userId)
    {
        return $this->Cache('user_' . $userId . '_image_albums', function () use ($userId)
        {
            return Image::whereRaw('user_id = ? and album_id = 0 and image_count <> 0', [$userId])
                ->get()
                ->toArray();
        }, 'm');
    }

    public function followedBangumis($userId, $page = -1, $count = 10)
    {
        $bangumiFollowService = new BangumiFollowService();
        $bangumiIds = $bangumiFollowService->usersDoIds($userId, $page, $count);

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
        // 帖子的回复数（不包括楼层评论）
        $postReplyCount = DB::table('post_comments')
            ->where('created_at', '<', $createdAt)
            ->where('modal_id', '<>', 0)
            ->count();
        $this->setDayStats('create_post_reply', $yesterday, $postReplyCount);
        // 帖子里的图片数
        $postImageCount = PostImages::where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('create_post_image', $yesterday, $postImageCount);
        // imageCount
        $imageCount = Image::where('image_count', 0)
            ->where('created_at', '<', $createdAt)
            ->count();
        $this->setDayStats('create_image', $yesterday, $imageCount);
        // album_count
        $albumCount = Image::where('created_at', '<', $createdAt)
            ->where('album_id', 0)
            ->where('image_count', '>', 1)
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
}