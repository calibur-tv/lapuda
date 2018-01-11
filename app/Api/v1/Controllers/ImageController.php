<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\ImageRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Banner;

/**
 * @Resource("图片相关接口")
 */
class ImageController extends Controller
{
    /**
     * 获取首页banner图
     *
     * @Get("/image/banner")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "图片列表"})
     * })
     */
    public function banner()
    {
        $list = Cache::remember('index_banner', config('cache.ttl'), function () {
            return Banner::select('id', 'url', 'user_id', 'bangumi_id', 'gray')->get()->toArray();
        });

        shuffle($list);

        return $this->res($list);
    }

    /**
     * 获取 Geetest 验证码
     *
     * @Post("/image/captcha")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"id": "Geetest.gt", "secret": "Geetest.challenge", "access": "认证密匙"}})
     * })
     */
    public function captcha()
    {
        $token = rand(0, 100) . microtime() . rand(0, 100);

        return $this->res([
            'id' => config('geetest.id'),
            'secret' => md5(config('app.key', config('geetest.key') . $token)),
            'access' => $token
        ]);
    }

    /**
     * 获取图片上传token
     *
     * @Post("/image/uptoken")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"upToken": "上传图片的token", "expiredAt": "token过期时间戳，单位为s"}}),
     *      @Response(401, body={"code": 401, "data": "未登录的用户"})
     * })
     */
    public function uptoken()
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->res('未登录的用户', 401);
        }

        $repository = new ImageRepository();

        return $this->res($repository->uptoken());
    }
}
