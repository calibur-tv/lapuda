<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteItem extends Model
{
   public function vote()
   {
       return $this->belongsTo(Vote::class);
   }
}
