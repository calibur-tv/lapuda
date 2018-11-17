<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/11/17
 * Time: 下午9:28
 */

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Socialite;


class OvertrueSocialite extends Socialite
{
    protected function createDefaultRequest()
    {
        $request = Request::createFromGlobals();
        $session = new Redis();

        $request->setSession($session);

        return $request;
    }
}