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
        return (int) JWTAuth::getPayload()['sub'];
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

    protected function resErrBad($message = null, $data = '')
    {
        return response([
            'code' => 40004,
            'message' => $message ?: config('error.40004'),
            'data' => $data
        ], 400);
    }

    protected function resErrRole($message = null)
    {
        return response([
            'code' => 40301,
            'message' => $message ?: config('error.40301'),
            'data' => ''
        ], 403);
    }

    protected function resErrParams($data = '')
    {
        return response([
            'code' => 40003,
            'message' => config('error.40003'),
            'data' => $data
        ], 400);
    }

    protected function resErrNotFound($message = null)
    {
        return response([
            'code' => 40401,
            'message' => $message ?: config('error.40401'),
            'data' => ''
        ], 404);
    }
}
