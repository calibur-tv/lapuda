<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午3:56
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VirtualIdolDeal extends Model
{
    use SoftDeletes;

    protected $table = 'virtual_idol_deals';

    protected $fillable = [
        'idol_id',
        'user_id',
        'product_count',
        'product_price',
        'last_count'
    ];
}
