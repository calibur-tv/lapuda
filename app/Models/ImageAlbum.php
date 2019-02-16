<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImageAlbum extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'album_id',
        'image_id',
        'rank',
    ];
}
