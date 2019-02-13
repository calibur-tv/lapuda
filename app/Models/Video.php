<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'url',
        'name',
        'poster',
        'bangumi_id',
        'part',
        'episode',
        'bangumi_season_id',
        'resource',
        'count_played',
        'baidu_cloud_src',
        'baidu_cloud_pwd'
    ];

    protected $casts = [
        'part' => 'integer'
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
