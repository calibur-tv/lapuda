<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/16
 * Time: 下午9:49
 */

namespace App\Api\V1\Services\Counter\Stats;


use App\Api\V1\Services\Counter\Base\TotalCounterService;

class TotalScoreCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct('scores', 'score');
    }
}