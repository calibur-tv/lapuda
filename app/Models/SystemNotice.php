<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotice extends Model
{
    protected $table = 'system_notices';

    protected $fillable = [
        'title',
        'banner',
        'content'
    ];
}
