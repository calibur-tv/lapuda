<?php

namespace App\Api\V1\Services\Counter\Stats;

use App\Api\V1\Services\Counter\Base\TotalCounterService;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 上午6:27
 */
class TotalUserCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct('users', 'user');
    }
}