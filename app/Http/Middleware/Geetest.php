<?php

namespace App\Http\Middleware;

use Closure;

class Geetest
{
    public function handle($request, Closure $next)
    {
        $geetest = $request->get('geetest');
        $time = $geetest['access'];

        if ((time() - $time) > 30000)
        {
            return response([
                'code' => 403,
                'data' => '验证码过期，请刷新网页重试',
                'message' => ''
            ], 403);
        }

        if (md5(config('geetest.key') . $geetest['access']) === $geetest['secret'])
        {
            return $next($request);
        }

        return response([
            'code' => 403,
            'data' => '错误的验证码',
            'message' => ''
        ], 403);
    }
}