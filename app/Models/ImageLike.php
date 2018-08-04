<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageLike extends Model
{
    protected $table = 'image_likes';

    protected $fillable = ['user_id', 'modal_id'];
}
