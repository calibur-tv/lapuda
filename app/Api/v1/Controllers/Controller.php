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

    protected function resErrFreeze($time)
    {
        $expired = strtotime($time) - time();
        $text = $expired > 3600 ? intval($expired / 3600) . '个小时' : intval($expired / 60) . '分钟';

        return response([
            'code' => 40302,
            'message' => '你已被禁言，离解禁还有：' . $text
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

    protected function resErrLocked($message = null)
    {
        return response([
            'code' => 42301,
            'message' => $message ?: config('error.42301')
        ], 423);
    }

    protected function resErrServiceUnavailable($message = null)
    {
        return response([
            'code' => 50301,
            'message' => $message ?: config('error.50301')
        ], 503);
    }

    protected function filterIdsByMaxId($ids, $maxId, $take)
    {
        $offset = $maxId ? array_search($maxId, $ids) + 1 : 0;
        $total = count($ids);

        return [
            'ids' => array_slice($ids, $offset, $take),
            'total' => $total,
            'noMore' => $total - ($offset + $take) <= 0
        ];
    }

    protected function filterIdsBySeenIds($ids, $seenIds, $take)
    {
        $result = array_slice(array_diff($ids, $seenIds), 0, $take);
        $total = count($ids);

        return [
            'ids' => $result,
            'total' => $total,
            'noMore' => count($result) < $take
        ];
    }

    protected function filterIdsByPage($ids, $page, $take)
    {
        $ids = gettype($ids) === 'string' ? explode(',', $ids) : $ids;
        $result = array_slice($ids, $page * $take, $take);
        $total = count($ids);

        return [
            'ids' => $result,
            'total' => $total,
            'noMore' => $total - ($page + 1) * $take <= 0
        ];
    }

    protected function createShareLink($model, $id, $currentUserId = 0)
    {
        $link = "https://m.calibur.tv/{$model}/{$id}";

        if ($currentUserId)
        {
            $time = time() + 3600; // 1小时内有效
            $key = md5($currentUserId . '-the-world-' . $time);
            $link = "{$link}?uid={$currentUserId}&time={$time}&key={$key}";
        }

        return $link;
    }

    protected function cacheShareLink($model, $id)
    {
        return "https://m.calibur.tv/{$model}/{$id}";
    }
}
