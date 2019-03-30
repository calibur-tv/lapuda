<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午3:56
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualIdolDailyIncome extends Model
{
    public $timestamps = false;

    protected $table = 'virtual_idol_daily_income';

    protected $fillable = [
        'idol_id',
        'get',
        'set',
        'balance',
        'day'
    ];
}
