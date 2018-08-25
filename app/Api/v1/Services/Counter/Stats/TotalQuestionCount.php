<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/24
 * Time: 下午8:06
 */

namespace App\Api\V1\Services\Counter\Stats;


use App\Api\V1\Services\Counter\Base\TotalCounterService;

class TotalQuestionCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct('questions', 'question');
    }
}