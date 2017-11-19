<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Tymon\JWTAuth\Facades\JWTAuth;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

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

    protected function resOK($data = '', $message = '', $code = 0)
    {
        return response([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ], $code ? $code : 200);
    }
}
