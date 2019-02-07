<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午3:56
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualCoin extends Model
{
    protected $table = 'virtual_coins';

    /**
     * to_product_type
     * 0 => 签到
     * 1 => 邀请注册
     * 2 => 普通用户活跃送团子
     * 3 => 吧主活跃送团子
     * 4 => 打赏帖子
     * 5 => 打赏相册
     * 6 => 打赏漫评
     * 7 => 打赏回答
     * 8 => 打赏视频
     * 9 => 应援偶像
     * 10 => 提现
     * 11 => 发表的内容被删除导致光玉被冻结
     * 12 => 账户被封禁
     * 13 => 给用户赠送团子
     * 14 => 给用户赠送光玉
     * 15 => 活跃用户送光玉
     * 16 => 活跃吧主送光玉
     * 17 => 被别人邀请送团子
     * 18 => 承包季度视频
     * 19 => 撤销应援
     */
    protected $fillable = [
        'amount',
        'user_id',
        'about_user_id',
        'channel_type',
        'product_id'
    ];
}
