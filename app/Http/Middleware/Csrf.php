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

    protected $methods = [
        'HEAD', 'GET', 'OPTIONS'
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
            in_array($request->method(), $this->methods) ||
            in_array($request->headers->get('Origin'), $this->domains) ||
            in_array($request->url(), $this->except) ||
            (
                in_array($request->headers->get('X-Auth-Timestamp'), $this->apps) &&
                md5(config('app.md5_salt') . $request->headers->get('X-Auth-Timestamp')) === $request->headers->get('X-Auth-Token')
            )
        ) {
            return $next($request);
        }
        return response('token mismatch exception', 503);
    }
}
