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
        if (config('app.env') !== 'production')
        {
            \Log::info('request url: ' . $request->url());
            \Log::info('request ip: ' . $request->ip());
            \Log::info('request ua: ' . $request->header('User-Agent'));
            \Log::info('request domain: ' . $request->headers->get('Origin'));
            \Log::info('request auth: ' . $request->headers->get('Authorization'));
        }

        if (
            config('app.env') === 'local' ||
            in_array($request->method(), $this->methods) ||
            in_array($request->headers->get('Origin'), $this->domains) ||
            in_array($request->url(), $this->except) ||
            (
                time() - intval($request->headers->get('X-Auth-Timestamp')) < 60 &&
                md5(config('app.md5_salt') . $request->headers->get('X-Auth-Timestamp')) === $request->headers->get('X-Auth-Token')
            )
        ) {
            return $next($request);
        }

        return response([
            'code' => 40101,
            'message' => config('error.40101'),
            'data' => ''
        ], 401);
    }
}
