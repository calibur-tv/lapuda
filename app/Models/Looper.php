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

class Looper extends Model
{
    use SoftDeletes;

    protected $table = 'cm_looper';

    protected $fillable = [
        'title',
        'desc',
        'poster',
        'link',
        'view_count',
        'click_count',
        'begin_at',
        'end_at'
    ];

    protected $casts = [
        'id' => 'integer'
    ];
}
