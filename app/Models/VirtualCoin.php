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

    protected $fillable = [
        'amount',
        'user_id',
        'from_user_id',
        'from_channel_type',
        'product_id'
    ];
}
