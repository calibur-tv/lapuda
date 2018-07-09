<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/3
 * Time: 上午6:13
 */

namespace App\Api\V1\Services\Counter;


use App\Api\V1\Services\Counter\Base\MigrateCounterService;

class VideoPlayCounter extends MigrateCounterService
{
    public function __construct()
    {
        parent::__construct('videos', 'count_played');
    }
}