<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 上午6:45
 */

namespace App\Api\V1\Services\Counter\Stats;


class TotalImageCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct('images', 'image');
    }
}