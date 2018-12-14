<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    public function items()
    {
        return $this->hasMany(VoteItem::class);
    }
}
