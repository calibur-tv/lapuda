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

class VirtualIdolPriceDraft extends Model
{
    use SoftDeletes;

    protected $table = 'virtual_idol_price_drafts';

    protected $fillable = [
        'idol_id',
        'user_id',
        'stock_price',
        'add_stock_count',
        'result'
    ];
}
