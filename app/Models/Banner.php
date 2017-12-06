<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    use SoftDeletes;

    protected $fillable = ['url', 'user_id', 'bangumi_id', 'gray'];

    protected $casts = [
        'gray' => 'integer'
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function bangumi()
    {
        return $this->hasOne(Bangumi::class);
    }

    public function getUrlAttribute($url)
    {
        return config('website.cdn') . $url;
    }
}
