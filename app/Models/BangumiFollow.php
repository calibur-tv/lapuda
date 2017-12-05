<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/3
 * Time: 下午11:04
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BangumiFollow extends Model
{
    use SoftDeletes;

    protected $table = 'bangumi_follows';

    protected $fillable = [
        'user_id',
        'bangumi_id'
    ];
}