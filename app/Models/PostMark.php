<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostMark extends Model
{
    use SoftDeletes;

    protected $table = 'post_mark';

    protected $fillable = [
        'user_id',
        'post_id'
    ];
}
