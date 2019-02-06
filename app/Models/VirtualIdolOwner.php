<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午3:56
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualIdolOwner extends Model
{
    protected $table = 'virtual_idol_owners';

    protected $fillable = [
        'idol_id',
        'user_id',
        'stock_count', // 持有的股数
        'total_price', // 持有股数对应的总价
    ];
}
