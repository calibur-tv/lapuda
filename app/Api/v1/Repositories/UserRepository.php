<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Api\V1\Repositories;

use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\BangumiFollow;
use App\Models\Notifications;
use App\Models\Post;
use App\Models\User;
use App\Models\UserCoin;
use App\Models\UserSign;
use Carbon\Carbon;

class UserRepository extends Repository
{
    public function item($id)
    {
        return $this->RedisHash('user_'.$id, function () use ($id)
        {
            $user = User::findOrFail($id)->toArray();
            $user['sex'] = $this->maskSex($user['sex']);

            return $user;
        });
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
            $result[] = $this->item($id);
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

    public function bangumis($userId)
    {
        $ids = $this->RedisList('user_'.$userId.'_followBangumiIds', function () use ($userId)
        {
           return  BangumiFollow::where('user_id', $userId)
               ->orderBy('created_at', 'DESC')
               ->pluck('bangumi_id');
        });

        if (empty($ids))
        {
            return [];
        }

        $bangumiRepository = new BangumiRepository();
        $data = $bangumiRepository->list($ids);

        foreach ($data as $i => $item)
        {
            $data[$i]['followed'] = true;
        }

        $bangumiTransformer = new BangumiTransformer();

        return $bangumiTransformer->list($data);
    }

    public function minePostIds($userId)
    {
        return $this->RedisList('user_'.$userId.'_minePostIds', function () use ($userId)
        {
           return Post::whereRaw('parent_id = ? and user_id = ?', [0, $userId])
               ->orderBy('created_at', 'DESC')
               ->pluck('id');
        });
    }

    public function daySigned($userId)
    {
        return UserSign::whereRaw('user_id = ? and created_at > ?', [$userId, Carbon::now()->startOfDay()])->count() !== 0;
    }

    public function replyPostIds($userId)
    {
        return $this->RedisList('user_'.$userId.'_replyPostIds', function () use ($userId)
        {
            return Post::whereRaw('parent_id <> ? and user_id = ?', [0, $userId])
                ->whereNotIn('target_user_id', [$userId, 0])
                ->orderBy('created_at', 'DESC')
                ->pluck('id');
        });
    }

    public function replyPostItem($userId, $postId)
    {
        return $this->Cache('user_'.$userId.'_replyPost_'.$postId, function () use ($postId)
        {
            $data = Post::where('id', $postId)
                ->select('id', 'parent_id', 'content', 'like_count', 'created_at', 'target_user_id', 'floor_count')
                ->first()
                ->toArray();

            $postRepository = new PostRepository();
            $postTransformer = new PostTransformer();
            $bangumiRepository = new BangumiRepository();

            $parent = $postRepository->item($data['parent_id']);
            if ($parent['parent_id'] !== '0')
            {
                $post = $postRepository->item($parent['parent_id']);
            }
            else
            {
                $post = $parent;
            }
            $data['post'] = $post;
            $data['parent'] = $parent;
            $data['images'] = $postRepository->images($data['id']);
            $data['user'] = $this->item($data['target_user_id']);
            $data['bangumi'] = $bangumiRepository->item($post['bangumi_id']);

            return $postTransformer->userReply($data);
        });
    }

    public function toggleCoin($isDelete, $fromUserId, $toUserId, $type)
    {
        if ($fromUserId == $toUserId)
        {
            return false;
        }

        if ($isDelete)
        {
            $log = UserCoin::whereRaw('from_user_id = ? and user_id = ? and type = ?', [$fromUserId, $toUserId, $type])->first();
            if (is_null($log))
            {
                return false;
            }

            $log->delete();
            User::where('id', $toUserId)->increment('coin_count', -1);
        }
        else
        {
            $count = User::where('id', $fromUserId)->pluck('coin_count')->first();
            if ($count <= 0)
            {
                return false;
            }
            UserCoin::create([
                'user_id' => $toUserId,
                'from_user_id' => $fromUserId,
                'type' => $type
            ]);
            User::where('id', $toUserId)->increment('coin_count', 1);
            User::where('id', $fromUserId)->increment('coin_count', -1);
        }

        return true;
    }

    public function getNotificationCount($userId)
    {
        return Notifications::whereRaw('to_user_id = ? and checked = ?', [$userId, false])->count();
    }

    public function getNotificationIds($userId)
    {
        return Notifications::where('to_user_id', $userId)
            ->orderBy('id', 'DESC')
            ->pluck('id')
            ->toArray();
    }

    public function getNotification($id)
    {
        return $this->Cache('notification_'.$id, function () use ($id)
        {
            $data = Notifications::where('id', $id)->first();

            $userRepository = new UserRepository();
            $user = $userRepository->item($data['from_user_id']);
            $type = $data['type'];
            $parent = null;

            if ($type === 1)
            {
                $postRepository = new PostRepository();
                $post = $postRepository->item($data['about_id']);
                $about = [
                    'title' => $post['title'],
                    'id' => $post['id']
                ];
                $model = 'post';
            }
            else if ($type === 2)
            {
                $postRepository = new PostRepository();
                $post = $postRepository->item($data['parent_id']);
                $about = [
                    'id' => $data['about_id'],
                    'title' => $post['title'],
                ];
                $parent = [
                    'id' => $post['id']
                ];
                $model = 'post';
            }

            $transformer = new UserTransformer();

            return $transformer->notification([
                'model' => $model,
                'type' => $type,
                'user' => $user,
                'about' => $about,
                'parent' => $parent
            ]);
        });
    }
}