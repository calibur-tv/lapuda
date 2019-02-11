<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午3:56
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualIdolDealRecord extends Model
{
    protected $table = 'virtual_idol_deal_records';

    protected $fillable = [
        'idol_id',
        'from_user_id',
        'buyer_id',
        'deal_id',
        'exchange_amount',
        'exchange_count'
    ];
}
