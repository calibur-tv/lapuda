<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/8
 * Time: 下午9:14
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlbumImage extends Model
{
    use SoftDeletes;

    protected $table = 'album_images';

    protected $fillable = [
        'user_id',
        'album_id',
        'url',
        'width',
        'height',
        'size',
        'type',
        'state'
    ];

    public function getUrlAttribute($url)
    {
        return config('website.image') . ($url ? $url : 'avatar');
    }
}
