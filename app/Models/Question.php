<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 上午6:35
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $table = 'questions';

    protected $fillable = ['user_id', 'title', 'content', 'intro', 'state'];
}
