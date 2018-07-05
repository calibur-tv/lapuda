<?php

namespace App\Http\Middleware;

use Closure;

class Csrf
{
    protected $domains = [
        'https://www.calibur.tv',
        'https://m.calibur.tv',
        'https://t-www.calibur.tv',
        'https://t-m.calibur.tv',
        ''
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
        if (!in_array($request->headers->get('Origin'), $this->domains))
        {
            \Log::info('request domain: ' . $request->headers->get('Origin'));
        }

        if (config('app.env') === 'local')
        {
            return $next($request);
        }

        if (in_array($request->method(), $this->methods))
        {
            return $next($request);
        }

        if (in_array($request->headers->get('Origin'), $this->domains))
        {
            return $next($request);
        }

        if (in_array($request->url(), $this->except))
        {
            return $next($request);
        }

//        if (
//            time() - intval($request->headers->get('X-Auth-Timestamp')) < 60 &&
//            md5(config('app.md5_salt') . $request->headers->get('X-Auth-Timestamp')) === $request->headers->get('X-Auth-Token')
//        ) {
//            return $next($request);
//        }

        return $next($request);
//        return response([
//            'code' => 40101,
//            'message' => config('error.40101')
//        ], 401);
    }
}
