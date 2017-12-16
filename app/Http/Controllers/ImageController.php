<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ImageController extends Controller
{
    public function token(Request $request)
    {
        $setKey = $request->get('setKey');

        if ($setKey)
        {
            $validator = Validator::make($request->all(), [
                'modal' => [
                    'required',
                    Rule::in(['user', 'bangumi', 'post']),
                ],
                'type' => 'required|string',
                'id' => 'required|integer'
            ]);

            if ($validator->fails())
            {
                return $this->resErr(['参数校验失败']);
            }
        }

        $auth = new \App\Services\Qiniu\Auth();

        $key = $setKey
            ? "{$request->get('modal')}/{$request->get('type')}/{$request->get('id')}/".time()
            : null;

        $token = $auth->uploadToken($key);

        return $this->resOK([
            'token' => $token,
            'key' => $key
        ]);
    }
}
