<?php

namespace App\Http\Middleware;

use Closure;

class Csrf
{
    protected $allows = [
        'https://www.riuir.com',
        'https://m.riuir.com',
        'http://riuir.dev',
        '127.0.0.1',
        ''
    ];

    protected $except = [];

    public function handle($request, Closure $next)
    {
        if (
            in_array($request->method(), ['HEAD', 'GET', 'OPTIONS']) ||
            in_array($request->headers->get('Origin'), $this->allows) ||
            in_array($request->url(), $this->except) ||
            md5(config('app.md5_salt') . $request->headers->get('X-Auth-Timestamp')) === $request->headers->get('X-Auth-Token')
        ) {
            return $next($request);
        }
        return response('token mismatch exception', 503);
    }
}
