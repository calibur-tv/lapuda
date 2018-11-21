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

            return JWTAuth::parseToken()->authenticate();

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return null;

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return null;

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {

            return null;

        }
    }

    protected function getAuthUserId()
    {
        try {
            return (int)JWTAuth::parseToken()->getPayload()['sub'];
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return 0;
        }
    }
}
