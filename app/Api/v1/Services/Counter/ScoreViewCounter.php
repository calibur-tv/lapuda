<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/21
 * Time: 下午9:07
 */

namespace App\Api\V1\Services\Counter;

use App\Api\V1\Services\Counter\Base\MigrateCounterService;

class ScoreViewCounter extends MigrateCounterService
{
    public function __construct()
    {
        parent::__construct('scores', 'view_count');
    }
}