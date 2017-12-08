<?php

namespace App\Http\Controllers;

use App\Http\Requests\Image\UpTokenRequest;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function token(UpTokenRequest $request)
    {
        $auth = new \App\Services\Qiniu\Auth();

        $key = "{$request->get('modal')}/{$request->get('type')}/{$request->get('id')}/".time();
        $token = $auth->uploadToken($key);

        return $this->resOK([
            'token' => $token,
            'key' => $key
        ]);
    }
}
