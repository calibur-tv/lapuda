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
        'type_id',
        'count'
    ];

    /**
     * type
     * 0： 每日签到（old）
     * 1： 帖子
     * 2： 邀请用户注册
     * 3： 为偶像应援
     * 4： 图片
     * 5： 提现
     * 6： 漫评
     * 7： 回答
     * 8： 每日签到（new）
     * 9： 删除帖子
     * 10：删除图片
     * 11：删除漫评
     * 12：删除回答
     * 13：视频
     * 14：删除视频
     * 15：普通用户100战斗力送团子
     * 16：番剧管理者100战斗力送团子
     */

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'from_user_id' => 'integer',
        'type_id' => 'integer',
        'type' => 'integer'
    ];
}
