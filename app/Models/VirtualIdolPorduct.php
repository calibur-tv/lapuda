<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午3:56
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualIdolPorduct extends Model
{
    protected $table = 'virtual_idol_products';

    protected $fillable = [
        'idol_id', // 偶像id
        'buyer_id', // 采购人id
        'author_id', // 作者id
        'product_id', // 产品id
        'product_type', // 产品类型，0 => 帖子，1 => 漫评
        'amount', // 采购价格
        'result', // 购买结果，0 => 等待，1 => 同意，2 => 拒绝，3 => 取消，4 => 已售，5 => 已失效
        'income_ratio' // 盈利占比，1 ~ 99 的数，代表偶像获得的分成比例
    ];

    protected $casts = [
        'result' => 'integer'
    ];
}
