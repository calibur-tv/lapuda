<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    protected $fillable = [
        'title', 'description', 'post_id', 'expired_at', 'multiple'
    ];

    public function items()
    {
        return $this->hasMany(VoteItem::class);
    }
}
