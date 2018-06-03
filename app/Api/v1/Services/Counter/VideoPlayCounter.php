<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/3
 * Time: 上午6:13
 */

namespace App\Api\V1\Services\Counter;


class VideoPlayCounter extends CounterService
{
    public function __construct()
    {
        parent::__construct('videos', 'count_played');
    }
}