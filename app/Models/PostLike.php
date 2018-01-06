<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostLike extends Model
{
    use SoftDeletes;

    protected $table = 'post_like';

    protected $fillable = [
        'user_id',
        'post_id'
    ];
}
