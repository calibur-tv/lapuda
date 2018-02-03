<?php

namespace App\Http\Middleware;

use Closure;

class Geetest
{
    public function handle($request, Closure $next)
    {
        $geetest = $request->get('geetest');
        $time = $geetest['access'];

        if ((time() - $time) > 15)
        {
            return response([
                'code' => 40001,
                'message' => config('error.40001'),
                'data' => '',
            ], 400);
        }

        if (md5(config('geetest.key') . $geetest['access']) === $geetest['secret'])
        {
            return $next($request);
        }

        return response([
            'code' => 40002,
            'message' => config('error.40002'),
            'data' => ''
        ], 400);
    }
}