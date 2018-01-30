<?php

namespace App\Api\V1\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Tymon\JWTAuth\Facades\JWTAuth;
use Dingo\Api\Routing\Helpers;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, Helpers;

    protected function getAuthUser()
    {
        try {

            if (! $user = JWTAuth::parseToken()->authenticate()) {

                return null;

            }

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return null;

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return null;

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {

            return null;

        }

        return JWTAuth::parseToken()->authenticate();
    }

    protected function getAuthUserId()
    {
        $user = $this->getAuthUser();
        return is_null($user) ? 0 : $user->id;
    }

    protected function resOK($data = '', $code = 0)
    {
        return response()
            ->json([
                'code' => $code,
                'data' => $data
            ], $code ? $code : 200, ['Content-type' => 'application/json; charset=utf-8'], JSON_UNESCAPED_UNICODE);
    }

    protected function resErr($data = '', $code, $message = '')
    {
        return response()
            ->json([
                'code' => $code,
                'data' => $data,
                'message' => $message
            ], $code, ['Content-type' => 'application/json; charset=utf-8'], JSON_UNESCAPED_UNICODE);
    }
}
