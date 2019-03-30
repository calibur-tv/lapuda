<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class LightCoin extends Model
{
    use SoftDeletes;

    protected $table = 'light_coins';

    /**
     * holder_type
     * 0 => 系统
     * 1 => 用户
     * 2 => 偶像
     */
    /**
     * origin_from
     * 0 => 签到
     * 1 => 邀请注册
     * 2 => 普通用户战斗力 > 100
     * 3 => 吧主战斗力 > 100
     * 4 => 氪金
     * 5 => 奖励
     */
    /**
     * state
     * 0 => 团子（可交易）
     * 1 => 光玉（可提现）
     * 2 => 石头（已消费）
     */
    protected $fillable = [
        'holder_id', // 持有者 id
        'holder_type', // 持有者类型
        'origin_from', // 团子的来源
        'state' // 流通的状态
    ];
}
