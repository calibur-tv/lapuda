<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/16
 * Time: 下午10:19
 */

namespace App\Api\V1\Services\Counter\Stats;


use App\Api\V1\Services\Counter\Base\TotalCounterService;

class TotalBangumiCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct('bangumis', 'bangumi');
    }
}