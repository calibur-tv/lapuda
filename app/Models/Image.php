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
    ];

    protected $casts = [
        'creator' => 'boolean',
        'role_id' => 'integer'
    ];

    public function getUrlAttribute($url)
    {
        return config('website.image').($url ? $url : 'avatar');
    }
}
