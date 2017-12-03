<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserRepository
{
    public function item($id)
    {
        return Cache::remember('user_'.$id.'_show', config('cache.ttl'), function () use ($id)
        {
            $user = User::find($id)->first()->toArray();
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
}