<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayStats extends Model
{
    public $timestamps = false;

    protected $table = 'day_stats';

    protected $fillable = ['type', 'day', 'count'];
}
