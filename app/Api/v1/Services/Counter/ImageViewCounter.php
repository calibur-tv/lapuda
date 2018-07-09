<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/3
 * Time: 上午9:39
 */

namespace App\Api\V1\Services\Counter;


use App\Api\V1\Services\Counter\Base\MigrateCounterService;

class ImageViewCounter extends MigrateCounterService
{
    public function __construct()
    {
        parent::__construct('images', 'view_count');
    }
}