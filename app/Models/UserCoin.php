<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCoin extends Model
{
    use SoftDeletes;

    protected $table = 'user_coin';

    protected $fillable = [
        'user_id',
        'from_user_id',
        'type',
        'type_id'
    ];

    /**
     * type
     * 0：每日签到
     * 1：帖子
     * 2：邀请用户注册
     * 3：为偶像应援
     * 4：为图片点赞
     */

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'from_user_id' => 'integer',
        'type_id' => 'integer',
        'type' => 'integer'
    ];
}
