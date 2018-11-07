<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午3:56
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppTemplate extends Model
{
    protected $table = 'app_templates';

    protected $fillable = [
        'version',
        'page',
        'url'
    ];
}
