<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteItemUser extends Model
{
    protected $fillable = [
        'vote_item_id',
        'vote_id',
        'user_id',
    ];
}
