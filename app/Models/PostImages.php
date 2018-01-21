<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostImages extends Model
{
    use SoftDeletes;

    protected $table = 'post_images';

    protected $fillable = [
        'post_id',
        'src',
        'type',
        'size',
        'width',
        'height'
    ];

    public function getSrcAttribute($url)
    {
        return config('website.image') . $url;
    }
}
