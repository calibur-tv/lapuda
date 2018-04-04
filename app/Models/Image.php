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
        'tag_id',
        'role_id',
        'creator',
        'url',
        'name',
        'like_count'
    ];
}
