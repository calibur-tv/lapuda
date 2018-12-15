<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteItem extends Model
{
    protected $fillable = [
        'title', 'vote_id',
    ];

   public function vote()
   {
       return $this->belongsTo(Vote::class);
   }
}
