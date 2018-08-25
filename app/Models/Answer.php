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

class Answer extends Model
{
    use SoftDeletes;

    protected $table = 'question_answers';

    protected $fillable = [
        'question_id',
        'user_id',
        'content',
        'intro',
        'published_at',
        'source_url'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'question_id' => 'integer'
    ];
}
