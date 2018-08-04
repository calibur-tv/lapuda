<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageTag extends Model
{
    public $timestamps = false;

    protected $table = 'image_tags';

    protected $fillable = ['tag_id', 'image_id'];
}
