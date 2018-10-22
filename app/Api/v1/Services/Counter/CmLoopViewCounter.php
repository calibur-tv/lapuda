<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午3:15
 */

namespace App\Api\V1\Services\Counter;


use App\Api\V1\Services\Counter\Base\MigrateCounterService;

class CmLoopViewCounter extends MigrateCounterService
{
    public function __construct()
    {
        parent::__construct('cm_looper', 'view_count');
    }
}