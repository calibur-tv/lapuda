<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class Csrf extends BaseVerifier
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
            md5(config('app.md5_salt') . $request->headers->get('X-Auth-Timestamp')) === $request->headers->get('X-Auth-Token')
        ) {
            return $next($request);
        }
        return response('token mismatch exception', 503);
    }
}
