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

    protected function resOK($data = '')
    {
        return response([
            'code' => 0,
            'data' => $data
        ], 200);
    }

    protected function resNoContent()
    {
        return response('', 204);
    }

    protected function resCreated($data)
    {
        return response([
            'code' => 0,
            'data' => $data
        ], 201);
    }

    protected function resErrBad($message = null)
    {
        return response([
            'code' => 40003,
            'message' => $message ?: config('error.40003')
        ], 400);
    }

    protected function resErrAuth($message = null)
    {
        return response([
            'code' => 40104,
            'message' => $message ?: config('error.40104')
        ], 401);
    }

    protected function resErrRole($message = null)
    {
        return response([
            'code' => 40301,
            'message' => $message ?: config('error.40301')
        ], 403);
    }

    protected function resErrParams($validator)
    {
        return response([
            'code' => 40003,
            'message' => $validator->errors()->all()[0]
        ], 400);
    }

    protected function resErrNotFound($message = null)
    {
        return response([
            'code' => 40401,
            'message' => $message ?: config('error.40401')
        ], 404);
    }

    protected function resErrServiceUnavailable($message = null)
    {
        return response([
            'code' => 50301,
            'message' => $message ?: config('error.50301')
        ], 503);
    }
}
