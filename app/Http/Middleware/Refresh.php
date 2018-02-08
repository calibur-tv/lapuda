<?php

namespace App\Http\Middleware;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/2/3
 * Time: 上午8:50
 */

use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class Refresh extends \Tymon\JWTAuth\Middleware\RefreshToken
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        try
        {
            $newToken = $this->auth->setRequest($request)->parseToken()->refresh();
        }
        catch (TokenExpiredException $e)
        {
            return response([
                'code' => 40102,
                'message' => config('error.40102'),
                'data' => ''
            ], 401);
        }
        catch (JWTException $e)
        {
            return response([
                'code' => 40103,
                'message' => config('error.40103'),
                'data' => ''
            ], 401);
        }

        $response->headers->set('Authorization', 'Bearer '.$newToken);

        return $response;
    }
}