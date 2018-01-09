<?php

namespace App\Http\Middleware;

use Closure;

class Geetest
{
    public function handle($request, Closure $next)
    {
        $geetest = $request->get('geetest');

        if (md5(config('app.key', config('geetest.key') . $geetest['access'])) === $geetest['secret'])
        {
            return $next($request);
        }

        return response([
            'code' => 403,
            'message' => ['验证码过期，请刷新网页重试'],
            'data' => ''
        ], 403);
    }
}