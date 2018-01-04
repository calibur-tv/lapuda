<?php

namespace App\Http\Middleware;

use Closure;

class Csrf
{
    protected $domains = [
        'https://www.calibur.tv',
        'https://m.calibur.tv',
        '127.0.0.1',
    ];

    protected $apps = [
        'innocence',
        'geass'
    ];

    protected $versions = [

    ];

    protected $except = [];

    public function handle($request, Closure $next)
    {
        if (
            config('app.env') !== 'production' ||
            in_array($request->method(), ['HEAD', 'GET', 'OPTIONS']) ||
            in_array($request->headers->get('Origin'), $this->domains) ||
            in_array($request->url(), $this->except) ||
            md5(config('app.md5_salt') . $request->headers->get('X-Auth-Timestamp')) === $request->headers->get('X-Auth-Token')
        ) {
            return $next($request);
        }
        return response('token mismatch exception', 503);
    }
}
