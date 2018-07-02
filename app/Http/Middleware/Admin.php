<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/2/4
 * Time: 上午9:24
 */

namespace App\Http\Middleware;


use Tymon\JWTAuth\Middleware\GetUserFromToken;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class Admin extends GetUserFromToken
{
    public function handle($request, \Closure $next)
    {
        if (! $token = $this->auth->setRequest($request)->getToken())
        {
            return response([
                'code' => 40104,
                'message' => config('error.40105')
            ], 401);
        }

        try
        {
            $user = $this->auth->authenticate($token);
        }
        catch (TokenExpiredException $e)
        {
            return response([
                'code' => 40104,
                'message' => config('error.40102')
            ], 401);
        }
        catch (JWTException $e)
        {
            return response([
                'code' => 40104,
                'message' => config('error.40103')
            ], 401);
        }

        if (! $user)
        {
            return response([
                'code' => 40104,
                'message' => config('error.40104')
            ], 401);
        }

        if (!$user->is_admin)
        {
            return response([
                'code' => 40301,
                'message' => config('error.40301')
            ], 403);
        }

        if ($this->auth->getPayload($token)['remember'] !== $user->remember_token)
        {
            return response([
                'code' => 40104,
                'message' => config('error.40106')
            ], 401);
        }

        return $next($request);
    }
}