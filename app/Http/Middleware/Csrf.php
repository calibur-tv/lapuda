<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class Csrf
{
    protected $domains = [
        'https://www.calibur.tv',
        'https://m.calibur.tv',
        'https://t-www.calibur.tv',
        'https://t-m.calibur.tv',
        'http://www.calibur.tv',
        'http://m.calibur.tv',
        ''
    ];

    public function handle($request, Closure $next)
    {

        if (config('app.env') === 'local')
        {
            return $next($request);
        }

        Log::info("request header x-app-name：" . $request->headers->get('X-APP-NAME'));
        Log::info("request header x-app-version：" . $request->headers->get('X-APP-VERSION'));

        if (in_array($request->headers->get('Origin'), $this->domains))
        {
            return $next($request);
        }

        return response([
            'code' => 40301,
            'message' => config('error.40301')
        ], 403);
    }
}
