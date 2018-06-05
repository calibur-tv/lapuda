<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostMark extends Model
{
    protected $table = 'post_mark';

    protected $fillable = [
        'user_id',
        'modal_id'
    ];
}
