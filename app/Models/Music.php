<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Music extends Model
{
    protected $table = 'musics';

    protected $fillable = [
        'src',
        'bangumi_id',
        'poster',
        'player',
        'name'
    ];

    public function getUrlAttribute($url)
    {
        if (!$url) {
            return '';
        }
        if (preg_match('/^(http|https)/', $url)) {
            return $url;
        }
        return config('website.video') . $url;
    }

    public function getPosterAttribute($poster)
    {
        return config('website.image') . $poster;
    }
}
