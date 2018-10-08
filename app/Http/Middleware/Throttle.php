<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/2/4
 * Time: 上午7:57
 */

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Tymon\JWTAuth\Facades\JWTAuth;

class Throttle extends ThrottleRequests
{
    public function handle($request, \Closure $next, $maxAttempts = 5, $decayMinutes = 10)
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts, $decayMinutes)) {
            return response([
                'code' => 42901,
                'message' => config('error.42901')
            ], 429);
        }

        $this->limiter->hit($key, $decayMinutes);

        $response = $next($request);

        return $this->addHeaders(
            $response, $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    protected function resolveRequestSignature($request)
    {
        if ($userId = $this->getAuthUserId()) {
            return sha1($request->url().'|'.$userId);
        }

        $ip = explode(', ', $request->headers->get('X-Forwarded-For'))[0];

        return sha1($request->url().'|'.$ip. '|' . $request->header('User-Agent'));
    }

    protected function getAuthUserId()
    {
        try {

            $payload = JWTAuth::parseToken()->getPayload();

            if (! $payload['sub'])
            {
                return 0;
            }

            return $payload['sub'];

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return 0;

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return 0;

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {

            return 0;

        }
    }
}