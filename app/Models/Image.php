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

class Image extends Model
{
    use SoftDeletes;

    protected $table = 'images';

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
        'state',
        'view_count'
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'bangumi_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'part' => 'integer',
        'state' => 'integer'
    ];

    public function getUrlAttribute($url)
    {
        return config('website.image') . ($url ? $url : 'avatar');
    }
}
