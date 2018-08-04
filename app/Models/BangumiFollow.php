<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/3
 * Time: 下午11:04
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BangumiFollow extends Model
{
    protected $table = 'bangumi_follows';

    protected $fillable = ['user_id', 'modal_id'];
}
