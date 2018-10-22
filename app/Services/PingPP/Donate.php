<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/10/21
 * Time: 上午10:24
 */

namespace App\Services\PingPP;


class Donate
{
    public function __construct()
    {
        Pingpp::setApiKey();
    }
}