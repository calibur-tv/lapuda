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
        'type'
    ];

    /**
     * type
     * 0：每日签到
     * 1：帖子
     */
}
