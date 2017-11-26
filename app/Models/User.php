<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'email',
        'nickname',
        'password',
        'zone',
        'avatar',
        'banner',
        'signature',
        'sex'
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function getAvatarAttribute($avatar)
    {
        return config('website.cdn').($avatar ? $avatar : 'default/user-avatar');
    }

    public function getBannerAttribute($banner)
    {
        return config('website.cdn').($banner ? $banner : 'default/user-banner');
    }

    public function getSexAttribute($sex)
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function sign()
    {
        return $this->hasOne(UserSign::Class);
    }

    /**是否签到了
     * @return bool
     */
    public function isSignToday()
    {
        // TODO 这里需要用缓存
        return $this->sign()->where('created_at','>',Carbon::now()->startOfDay())->first() != null;
    }

    /**是否签到成功
     * @return bool
     */
    public function signNow()
    {
        return with(new UserSign(), function (UserSign $sign) {
            $sign->user_id = $this->id;
            return $sign->save();
        });
    }
}
