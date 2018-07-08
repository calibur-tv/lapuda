<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/8
 * Time: 下午5:42
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImageV2 extends Model
{
    use SoftDeletes;

    protected $table = 'images_v2';

    protected $fillable = [
        'user_id',
        'bangumi_id',
        'is_cartoon',
        'is_creator',
        'is_album',
        'name',
        'url',
        'width',
        'height',
        'size',
        'type',
        'part',
        'state'
    ];

    public function getUrlAttribute($url)
    {
        return config('website.image') . ($url ? $url : 'avatar');
    }
}