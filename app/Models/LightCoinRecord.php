<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LightCoinRecord extends Model
{
    protected $table = 'light_coin_records';

    /**
     * from_user_id
     * [default]：0 => 系统
     */
    /**
     * to_user_id
     * [default]：0 => 系统
     */
    /**
     * to_product_id
     * [default]：0 => 批量产品，无 id
     */
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
     */
    protected $fillable = [
        'coin_id',          // 货币的id
        'order_id',         // 订单的id
        'from_user_id',     // 消费者id
        'to_user_id',       // 收费者id
        'to_product_id',    // 产品id
        'to_product_type',  // 产品的类型
        'order_amount'      // 订单的金额
    ];
}
