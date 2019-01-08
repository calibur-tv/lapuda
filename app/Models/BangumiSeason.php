<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BangumiSeason extends Model
{
    protected $table = 'bangumi_seasons';

    protected $fillable = [
        'bangumi_id',
        'name',
        'rank',
        'summary',
        'avatar',
        'videos',           // 存储用逗号分隔的 video_id
        'published_at',     // 改成时间格式
        'other_site_video',
        'released_at',
        'released_time',
        'end'
    ];

    protected $casts = [
        'rank' => 'integer',
        'released_at' => 'integer',
        'other_site_video' => 'boolean',
        'end' => 'boolean'
    ];

    /*
    public function getAvatarAttribute($avatar)
    {
        return config('website.image') . ($avatar ? $avatar : 'avatar');
    }
    */
}
