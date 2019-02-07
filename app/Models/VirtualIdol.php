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

class VirtualIdol extends Model
{
    use SoftDeletes;

    protected $table = 'cartoon_role';

    protected $fillable = [
        'name',
        'alias',
        'avatar',
        'intro',
        'boss_id',      // 大股东id
        'bangumi_id',   // 番剧id
        'manager_id',   // 经纪人id
        'stock_price',  // 每股的股价
        'star_count',   // 发行的总股数
        'fans_count',   // 持股人数
        'company_state',// 公司状态
    ];
}
