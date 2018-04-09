<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImageLike extends Model
{
    use SoftDeletes;

    protected $table = 'image_likes';

    protected $fillable = [
        'user_id',
        'image_id'
    ];
}
