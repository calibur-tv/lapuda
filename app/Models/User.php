<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'email',
        'nickname',
        'password',
        'zone',
        'avatar',
        'banner',
        'signature',
        'sex',
        'birthday',
        'coin_count',
        'state',    // 0 正常，1 待审
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    protected $casts = [
        'birthday' => 'integer',
        'sex' => 'integer'
    ];

    public function getAvatarAttribute($avatar)
    {
        return config('website.image').($avatar ? $avatar : 'default/user-avatar');
    }

    public function getBannerAttribute($banner)
    {
        return config('website.image').($banner ? $banner : 'default/user-banner');
    }
}
