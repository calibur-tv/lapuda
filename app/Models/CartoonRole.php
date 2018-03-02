<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartoonRole extends Model
{
    protected $table = 'cartoon_role';

    protected $fillable = [
        'bangumi_id',
        'avatar',
        'name',
        'intro',
        'alias',
        'star_count',
        'fans_count'
    ];

    protected $casts = [
        'bangumi_id' => 'integer',
        'fans_count' => 'integer',
        'star_count' => 'integer',
    ];

    public function getAvatarAttribute($avatar)
    {
        return config('website.image').($avatar ? $avatar : 'avatar');
    }
}
