<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Bangumi
 * @package App\Models
 */
class Bangumi extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'summary',
        'avatar',
        'banner',
        'alias',
        'season',
        'released_at',  // 周几更新
        'released_time',    // 更新时间
        'released_video_id',
        'published_at',
        'count_like',
        'count_score',
        'collection_id',
        'others_site_video',
        'end',
        'has_cartoon',
        'has_video',
        'cartoon'   // 漫画的 ids
    ];

    protected $casts = [
        'released_at' => 'integer',
        'released_video_id' => 'integer',
        'published_at' => 'integer',
        'collection_id' => 'integer',
        'others_site_video' => 'boolean',
        'end' => 'boolean',
        'has_video' => 'boolean',
        'has_cartoon' => 'boolean',
    ];

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getAvatarAttribute($avatar)
    {
        return config('website.image').($avatar ? $avatar : 'avatar');
    }

    public function getBannerAttribute($banner)
    {
        return config('website.image').($banner ? $banner : 'B-banner');
    }
}
