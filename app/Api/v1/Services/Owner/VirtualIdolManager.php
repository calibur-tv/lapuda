<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/5
 * Time: 下午6:53
 */

namespace App\Api\V1\Services\Owner;


use App\Api\V1\Services\Owner\Base\OwnerService;

class VirtualIdolManager extends OwnerService
{
    public function __construct()
    {
        parent::__construct('virtual_idol_managers', 5);
    }
}