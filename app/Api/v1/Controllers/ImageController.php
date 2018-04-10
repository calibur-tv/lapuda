<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\ImageTransformer;
use App\Models\Image;
use App\Models\ImageTag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

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
        $repository = new ImageRepository();
        $transformer = new ImageTransformer();

        $list = $repository->banners();
        shuffle($list);

        return $this->resOK($transformer->indexBanner($list));
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
        $time = time();

        return $this->resOK([
            'id' => config('geetest.id'),
            'secret' => md5(config('geetest.key') . $time),
            'access' => $time
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
     *      @Response(401, body={"code": 40104, "message": "未登录的用户", "data": ""})
     * })
     */
    public function uptoken()
    {
        $repository = new ImageRepository();

        return $this->resOK($repository->uptoken());
    }

    public function uploadType()
    {
        $repository = new ImageRepository();

        return $this->resOK($repository->uploadImageTypes());
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumiId' => 'required|integer',
            'creator' => 'required|boolean',
            'url' => 'required|string',
            'width' => 'required|integer',
            'height' => 'required|integer',
            'size' => 'required|integer',
            'tags' => 'required|integer',
            'roleId' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $userId = $this->getAuthUserId();

        $now = Carbon::now();
        $id = Image::insertGetId([
            'user_id' => $userId,
            'bangumi_id' => $request->get('bangumiId'),
            'url' => $request->get('url'),
            'width' => $request->get('width'),
            'height' => $request->get('height'),
            'role_id' => $request->get('roleId'),
            'creator' => $request->get('creator'),
            'created_at' => $now,
            'updated_at' => $now
        ]);

        ImageTag::create([
            'image_id' => $id,
            'tag_id' => $request->get('size')
        ]);

        ImageTag::create([
            'image_id' => $id,
            'tag_id' => $request->get('tags')
        ]);

        // TODO：审核
        return $this->resCreated($id);
    }
}
