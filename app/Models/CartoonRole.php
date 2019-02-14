<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartoonRole extends Model
{
    use SoftDeletes;

    protected $table = 'cartoon_role';

    /**
     * company_state
     * 0 => 融资中
     * 1 => 已上市
     */
    protected $fillable = [
        'boss_id',
        'bangumi_id',
        'manager_id',
        'avatar',
        'name',
        'intro',
        'alias',
        'star_count',
        'fans_count',
        'lover_words',
        'company_state',
        'market_price',
        'stock_price',
        'max_stock_count',
        'last_edit_at',
        'qq_group'
    ];

    public function getAvatarAttribute($avatar)
    {
        return config('website.image') . ($avatar ? $avatar : 'avatar');
    }
}
