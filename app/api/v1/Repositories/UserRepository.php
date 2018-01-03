<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Api\V1\Repositories;

use App\Models\BangumiFollow;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserRepository extends Repository
{
    public function item($id)
    {
        return $this->RedisHash('user_'.$id.'_show', function () use ($id)
        {
            $user = User::find($id);
            $user['sex'] = $this->maskSex($user['sex']);

            return $user;
        });
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
        $ids = BangumiFollow::where('user_id', $userId)->pluck('bangumi_id');
        if (empty($ids))
        {
            return [];
        }

        $bangumiRepository = new BangumiRepository();

        return $bangumiRepository->list($ids);
    }
}