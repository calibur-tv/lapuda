<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/3
 * Time: 上午9:39
 */

namespace App\Api\V1\Services\Counter;


class ImageViewCounter extends CounterService
{
    public function __construct()
    {
        parent::__construct('images', 'view_count');
    }
}