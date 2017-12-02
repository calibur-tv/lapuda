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
            $user = User::find($id)->first();

            return $user;
        });
    }
}