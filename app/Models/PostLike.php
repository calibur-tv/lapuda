<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostLike extends Model
{
    protected $table = 'post_like';

    protected $fillable = ['user_id', 'modal_id'];
}
