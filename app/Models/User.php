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
        'birth_secret',
        'sex_secret',
        'coin_count',
        'state', // 0 正常，1 待审
        'password_change_at', // 密码最后修改时间
        'remember_token',
        'phone',
        'is_admin',
        'faker'
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'sex' => 'integer',
        'state' => 'integer'
    ];

    public function getAvatarAttribute($avatar)
    {
        return (
            config('website.image') .
            ($avatar ? $avatar : 'default/user-avatar')
        );
    }

    public function getSignatureAttribute($text)
    {
        return $text ? $text : '这个人还很神秘...';
    }

    public function getBannerAttribute($banner)
    {
        return (
            config('website.image') .
            ($banner ? $banner : 'default/user-banner')
        );
    }
}
