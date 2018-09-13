<?php

namespace App\Http\Middleware;

use App\Services\Geetest\Captcha;
use Closure;

class Geetest
{
    public function handle($request, Closure $next)
    {
        $geetest = $request->get('geetest');

        if (is_null($geetest))
        {
            return response([
                'code' => 40001,
                'message' => config('error.40001')
            ], 400);
        }

        $captcha = new Captcha();

        if ($captcha->validate($geetest))
        {
            return $next($request);
        }

        return response([
            'code' => 40100,
            'message' => config('error.40100')
        ], 401);
    }
}