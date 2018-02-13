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
use App\Models\PostLike;
use App\Models\PostMark;
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

    public function daySigned($userId)
    {
        return UserSign::whereRaw('user_id = ? and created_at > ?', [$userId, Carbon::now()->startOfDay()])->count() !== 0;
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

    public function likedPostIds($userId)
    {
        return $this->RedisList('user_'.$userId.'_likedPostIds', function () use ($userId)
        {
            return PostLike::where('user_id', $userId)
                ->orderBy('created_at', 'DESC')
                ->pluck('post_id AS id');
        });
    }

    public function markedPostIds($userId)
    {
        return $this->RedisList('user_'.$userId.'_markedPostIds', function () use ($userId)
        {
            return PostMark::where('user_id', $userId)
                ->orderBy('created_at', 'DESC')
                ->pluck('post_id AS id');
        });
    }

    public function replyPostItem($userId, $postId)
    {
        return $this->Cache('user_'.$userId.'_replyPost_'.$postId, function () use ($postId)
        {
            $data = Post::where('id', $postId)
                ->select('id', 'parent_id', 'content', 'created_at', 'target_user_id')
                ->first()
                ->toArray();

            $postRepository = new PostRepository();
            $postTransformer = new PostTransformer();
            $bangumiRepository = new BangumiRepository();

            $parent = $postRepository->item($data['parent_id']);
            if (intval($parent['parent_id']) !== 0)
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

            User::where('id', $toUserId)->increment('coin_count', 1);

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
                $about = [
                    'resource' => $item['about_id'],
                    'title' => $post['title'],
                    'link' => '/post/'.$postId . '#reply=' . $ids[0],
                ];
                $model = 'post';
            }
            else if ($type === 2)
            {
                $commentId = $ids[0];
                $replyId = $ids[1];
                $postId = $ids[2];
                $post = $postRepository->item($postId);
                $about = [
                    'resource' => $item['about_id'],
                    'title' => $post['title'],
                    'link' => '/post/'.$postId.'#reply='.$replyId.'comment='.$commentId,
                ];
                $model = 'post';
            } else if ($type === 3)
            {
                $post = $postRepository->item($item['about_id']);
                $about = [
                    'resource' => $item['about_id'],
                    'title' => $post['title'],
                    'link' => '/post/'.$ids[0]
                ];
                $model = 'post';
            } else if ($type === 4)
            {
                $post = $postRepository->item($ids[1]);
                $about = [
                    'resource' => $item['about_id'],
                    'title' => $post['title'],
                    'link' => '/post/'.$ids[1].'#reply='.$ids[0]
                ];
                $model = 'post';
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
}