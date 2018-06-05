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
                'code' => 40002,
                'message' => config('error.40002'),
                'data' => 'argument is null'
            ], 400);
        }

        $captcha = new Captcha();

        if ($captcha->validate($geetest))
        {
            return $next($request);
        }

        return response([
            'code' => 40002,
            'message' => config('error.40002'),
            'data' => 'validate failed'
        ], 400);
    }
}