<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/10/30
 * Time: 上午8:36
 */

namespace App\Api\V1\Services\Activity;


class UserActivity extends Activity
{
    public function __construct()
    {
        parent::__construct('user_day_activity');
    }
}