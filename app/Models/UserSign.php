<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**签到模型
 * Class UserSign
 * @package App\Models
 */
class UserSign extends Model
{
    protected $table = 'user_signs';

    protected $fillable = ['user_id'];
}
