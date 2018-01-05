<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Api\V1\Repositories;

use App\Api\V1\Transformers\BangumiTransformer;
use App\Models\BangumiFollow;
use App\Models\Post;
use App\Models\User;

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
           return  BangumiFollow::where('user_id', $userId)->pluck('bangumi_id');
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

    public function replyPostIds($userId)
    {
        return $this->RedisList('user_'.$userId.'_replyPostIds', function () use ($userId)
        {
            return Post::whereRaw('parent_id <> ? and user_id = ? and target_user_id <> ?', [0, $userId, $userId])
                ->orderBy('created_at', 'DESC')
                ->pluck('id');
        });
    }
}