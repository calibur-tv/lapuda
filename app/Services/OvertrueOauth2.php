<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/11/20
 * Time: 下午11:13
 */

namespace App\Services;

use Socialite;

class OvertrueOauth2 extends Socialite
{
    public function redirect($type)
    {
        $state = $type . '-' . md5(time());
        $redirectUrl = null;

        if (!is_null($redirectUrl)) {
            $this->redirectUrl = $redirectUrl;
        }

        if ($this->usesState()) {
            $state = $this->makeState();
        }

        return new RedirectResponse($this->getAuthUrl($state));
    }
}