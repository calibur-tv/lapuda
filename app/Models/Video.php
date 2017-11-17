<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model {
    use SoftDeletes;

    protected $fillable = [
        'url',
        'name',
        'poster',
        'bangumi_id',
        'part',
        'resource',
        'count_played',
        'count_comment'
    ];

    protected $casts = [
        'part' => 'integer'
    ];

    public function bangumi() {
        return $this->belongsTo(Bangumi::class, 'id', 'bangumi_id');
    }

    public function getUrlAttribute($url) {
        return $url ? config('website.cdn') . $url : '';
    }

    public function getPosterAttribute($poster) {
        return config('website.cdn') . $poster;
    }
}
