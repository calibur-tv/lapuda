<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\ImageRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Banner;

class ImageController extends Controller
{
    public function banner()
    {
        $data = Cache::remember('index_banner', config('cache.ttl'), function () {
            return Banner::select('id', 'url', 'user_id', 'bangumi_id', 'gray')->get()->toArray();
        });

        shuffle($data);

        return $this->resOK($data);
    }

    public function token()
    {
        $repository = new ImageRepository();

        return $this->resOK($repository->uptoken());
    }

    public function captcha()
    {
        $token = rand(0, 100) . microtime() . rand(0, 100);

        return $this->resOK([
            'id' => config('geetest.id'),
            'secret' => md5(config('app.key', config('geetest.key') . $token)),
            'access' => $token
        ]);
    }
}
