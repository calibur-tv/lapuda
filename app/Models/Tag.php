<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = [
        'model',    // 多态, 0 是番剧
        'name'      // 名称
    ];

    protected $casts = [
        'model' => 'integer'
    ];
}
