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

class Score extends Model
{
    use SoftDeletes;

    protected $table = 'scores';

    protected $fillable = [
        'bangumi_id',
        'user_id',
        'idol_id',
        'user_age',
        'user_sex',
        'content',
        'intro',
        'total',
        'lol',
        'cry',
        'fight',
        'moe',
        'sound',
        'vision',
        'role',
        'story',
        'express',
        'style',
        'state',
        'published_at',
        'title',
        'view_count'
    ];

    protected $casts = [
        'total' => 'integer',
        'lol' => 'integer',
        'cry' => 'integer',
        'fight' => 'integer',
        'moe' => 'integer',
        'sound' => 'integer',
        'vision' => 'integer',
        'role' => 'integer',
        'story' => 'integer',
        'express' => 'integer',
        'style' => 'integer',
        'state' => 'integer'
    ];
}
