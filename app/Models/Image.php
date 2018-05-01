<?php

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
        'role_id',
        'size_id',
        'creator',
        'url',
        'like_count',
        'height',
        'width',
        'state',    // 0：待审，1：通过，2：审核中，3：删除，4：用户举报
        'is_cartoon',   // 是不是漫画，默认不是
        'album_id',
        'image_count',  // 专辑里图片的个数，默认是 0 用来判断专辑封面
        'name',     // 图片或专辑的名称
        'images'    // 专辑里的图片 ids
    ];

    // 普通图片 album_id === 0 && image_count === 0
    // 专辑封面 album_id === 0 && image_count >= 1, image_count = 1 时是空专辑
    // 专辑图片 album_id !== 0

    protected $casts = [
        'creator' => 'boolean',
        'role_id' => 'integer'
    ];

    public function getUpdatedAtColumn()
    {
        return null;
    }

    public function getUrlAttribute($url)
    {
        return config('website.image').($url ? $url : 'avatar');
    }
}
