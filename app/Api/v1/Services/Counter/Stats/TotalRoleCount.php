<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/19
 * Time: 上午9:11
 */

namespace App\Api\V1\Services\Counter\Stats;


use App\Api\V1\Services\Counter\Base\TotalCounterService;

class TotalRoleCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct('cartoon_role', 'role');
    }
}