<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostImages extends Model
{
    protected $table = 'post_images_2';

    protected $fillable = [
        'post_id',
        'comment_id',
        'url',
        'type',
        'size',
        'width',
        'height',
        'origin_url'
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer'
    ];

    public function getUrlAttribute($url)
    {
        return config('website.image') . ($url ? $url : 'default/user-avatar');
    }
}
