<?php

namespace App\Http\Middleware;

use Closure;

class ShowDelete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $hash = $request->get('hash');
        $time = $request->get('time');

        if ($hash == md5('force-eye-' . $time) && is_numeric($time) && 60 > abs(intval($time) - time())) {
            $request->offsetSet('showDelete', 1);
        }
        return $next($request);
    }
}
