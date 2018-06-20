<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Counter\ImageViewCounter;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\ImageTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Bangumi;
use App\Models\Image;
use App\Models\ImageLike;
use App\Models\ImageTag;
use App\Services\Geetest\Captcha;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
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
     * @Get("/image/captcha")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"success": "数字0或1", "gt": "Geetest.gt", "challenge": "Geetest.challenge", "payload": "字符串荷载"}})
     * })
     */
    public function captcha()
    {
        $captcha = new Captcha();

        return $this->resOK($captcha->get());
    }

    /**
     * 获取图片上传token
     *
     * @Get("/image/uptoken")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"upToken": "上传图片的token", "expiredAt": "token过期时的时间戳，单位为s"}}),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户"})
     * })
     */
    public function uptoken()
    {
        $repository = new ImageRepository();

        return $this->resOK($repository->uptoken());
    }

    /**
     * 上传相册图片时，可选的 tag s和 size 列表
     *
     * @Get("/image/uploadType")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"size": "可选的图片尺寸分类", "tags": "可选的图片标签分类"}})
     * })
     */
    public function uploadType()
    {
        $repository = new ImageRepository();

        return $this->resOK($repository->uploadImageTypes());
    }

    /**
     * 上传相册图片
     *
     * > 图片对象示例：
     * 1. `key` 七牛传图后得到的 key，不包含图片地址的 host，如一张图片 image.calibur.tv/user/1/avatar.png，七牛返回的 key 是：user/1/avatar.png，将这个 key 传到后端
     * 2. `width` 图片的宽度，七牛上传图片后得到
     * 3. `height` 图片的高度，七牛上传图片后得到
     * 4. `size` 图片的尺寸，七牛上传图片后得到
     * 5. `type` 图片的类型，七牛上传图片后得到
     *
     * @Post("/image/upload")
     *
     * @Parameters({
     *      @Parameter("bangumiId", description="所选的番剧 id（bangumi.id）", type="integer", required=true),
     *      @Parameter("creator", description="是否是原创", type="boolean", required=true),
     *      @Parameter("images", description="图片对象列表", type="array", required=true),
     *      @Parameter("size", description="所选的尺寸 id（size.id）", type="integer", required=true),
     *      @Parameter("tags", description="所选的标签 id（tag.id）", type="integer", required=true),
     *      @Parameter("roleId", description="所选的角色 id（role.id）", type="integer", required=true),
     *      @Parameter("albumId", description="所选的相册 id（album.id）", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "图片对象列表"}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"})
     * })
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumiId' => 'required|integer',
            'creator' => 'required|boolean',
            'images' => 'required|array',
            'size' => 'required|integer',
            'tags' => 'required|integer',
            'roleId' => 'required|integer',
            'albumId' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();
        $albumId = $request->get('albumId');
        $images = $request->get('images');
        $bangumiId = $request->get('bangumiId');
        $roleId = $request->get('roleId');
        $sizeId = $request->get('size');
        $creator = $request->get('creator');
        $tagId = $request->get('tags');

        $isCartoon = false;
        if ($albumId)
        {
            $albumData = Image::where('id', $albumId)->pluck('is_cartoon')->first();
            if ($albumData)
            {
                $isCartoon = true;
            }
        }

        $now = Carbon::now();
        $ids = [];

        foreach ($images as $item)
        {
            $id = Image::insertGetId([
                'user_id' => $userId,
                'bangumi_id' => $bangumiId,
                'url' => $item['key'],
                'width' => $item['width'],
                'height' => $item['height'],
                'role_id' => $roleId,
                'size_id' => $sizeId,
                'creator' => $creator,
                'image_count' => 0,
                'album_id' => $albumId,
                'created_at' => $now,
                'updated_at' => $now,
                'state' => $isCartoon ? 1 : 0
            ]);

            ImageTag::create([
                'image_id' => $id,
                'tag_id' => $tagId
            ]);

            $ids[] = $id;

            if (!$isCartoon)
            {
                // 漫画不进审核
                $job = (new \App\Jobs\Trial\Image\Create($id));
                dispatch($job);
            }
        }

        $cacheKey = 'user_' . $userId . '_image_ids';
        if (Redis::EXISTS($cacheKey))
        {
            Redis::LPUSH($cacheKey, $ids);
        }

        if ($albumId)
        {
            Image::where('id', $albumId)->increment('image_count', count($ids));
            $cacheKey = 'user_image_' . $albumId;
            if (Redis::EXISTS($cacheKey))
            {
                Redis::HINCRBYFLOAT($cacheKey, 'image_count', count($ids));
            }

            $images = Image::where('id', $albumId)->pluck('images')->first();
            if (is_null($images))
            {
                Image::where('id', $albumId)
                    ->update([
                        'images' => implode(',', $ids)
                    ]);

                // 第一次传图
                $job = (new \App\Jobs\Push\Baidu('album/' . $albumId));
                dispatch($job);
            }
            else
            {
                Image::where('id', $albumId)
                    ->update([
                        'images' => $images . ',' . implode(',', $ids)
                    ]);

                // 不是第一次传图
                $job = (new \App\Jobs\Push\Baidu('album/' . $albumId, 'update'));
                dispatch($job);
            }
            Redis::DEL('image_album_' . $albumId . '_images');
        }

        $repository = new ImageRepository();
        $transformer = new ImageTransformer();
        $list = $repository->list($ids);
        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = false;
        }

        return $this->resCreated($transformer->waterfall($list));
    }

    /**
     * 编辑图片
     *
     * @Post("/image/edit")
     *
     * @Parameters({
     *      @Parameter("id", description="图片的 id", type="integer", required=true),
     *      @Parameter("bangumiId", description="所选的番剧 id（bangumi.id）", type="integer", required=true),
     *      @Parameter("size", description="所选的尺寸 id（size.id）", type="integer", required=true),
     *      @Parameter("tags", description="所选的标签 id（tag.id）", type="integer", required=true),
     *      @Parameter("roleId", description="所选的角色 id（role.id）", type="integer", required=true),
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "新的图片对象"}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"})
     * })
     */
    public function editImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'bangumiId' => 'required|integer',
            'size' => 'required|integer',
            'tags' => 'required|integer',
            'roleId' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();
        $imageId = $request->get('id');

        $image = Image::whereRaw('id = ? and user_id = ?', [$imageId, $userId])->first();

        if (is_null($image))
        {
            return $this->resErrNotFound();
        }

        $image->update([
            'bangumi_id' => $request->get('bangumiId'),
            'role_id' => $request->get('roleId'),
            'size_id' => $request->get('size')
        ]);

        ImageTag::where('image_id', $imageId)->delete();

        ImageTag::create([
            'image_id' => $imageId,
            'tag_id' => $request->get('tags')
        ]);

        Redis::DEL('user_image_' . $imageId . '_meta');
        Redis::DEL('user_image_' . $imageId);

        $imageRepository = new ImageRepository();
        $transformer = new ImageTransformer();

        $result = $imageRepository->item($imageId);
        $result['liked'] = false;

        return $this->resOK($transformer->waterfall([$result])[0]);
    }

    /**
     * 删除图片
     *
     * @Post("/image/delete")
     *
     * @Parameters({
     *      @Parameter("id", description="图片的 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40104, "message": "未登录的用户"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的图片"})
     * })
     */
    public function deleteImage(Request $request)
    {
        $userId = $this->getAuthUserId();
        $imageId = $request->get('id');

        $image = Image::whereRaw('user_id = ? and id = ?', [$userId, $imageId])->first();

        if (is_null($image))
        {
            return $this->resErrNotFound();
        }

        $image->delete();
        ImageTag::where('image_id', $imageId)->delete();

        $cacheKey = 'user_' . $userId . '_image_ids';
        if (Redis::EXISTS($cacheKey))
        {
            Redis::LREM($cacheKey, 1, $imageId);
        }
        Redis::DEL('user_image_' . $imageId);
        Redis::DEL('user_image_' . $imageId . '_meta');

        return $this->resNoContent();
    }

    /**
     * 举报图片
     *
     * @Post("/image/report")
     *
     * @Parameters({
     *      @Parameter("id", description="图片的 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(204)
     * })
     */
    public function report(Request $request)
    {
        Image::where('id', $request->get('id'))
            ->update([
                'state' => 4
            ]);

        return $this->resNoContent();
    }

    /**
     * 喜欢或取消喜欢图片
     *
     * @Post("/image/toggleLike")
     *
     * @Parameters({
     *      @Parameter("id", description="图片的 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "是否已点赞"}),
     *      @Response(400, body={"code": 40104, "message": "未登录的用户"}),
     *      @Response(403, body={"code": 40003, "message": "不能为自己的图片点赞|`原创图片`金币不足"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的图片"})
     * })
     */
    public function toggleLike(Request $request)
    {
        $userId = $this->getAuthUserId();
        $imageId = $request->get('id');

        $image = Image::where('id', $imageId)->first();

        if (is_null($image))
        {
            return $this->resErrNotFound();
        }

        if (intval($image['user_id']) === $userId)
        {
            return $this->resErrRole('不能为自己的图片点赞');
        }

        $imageLikeService = new ImageLikeService();
        $liked = $imageLikeService->check($userId, $imageId);

        if ($liked)
        {
            $imageLikeService->undo($userId, $imageId);

            return $this->resCreated(false);
        }

        if ((boolean)$image['creator'])
        {
            $userRepository = new UserRepository();
            $success = $userRepository->toggleCoin(false, $userId, $image['user_id'], 4, $imageId);

            if (!$success)
            {
                return $this->resErrRole('金币不足');
            }
        }

        $likeId = $imageLikeService->do($userId, $imageId);

        $job = (new \App\Jobs\Notification\Image\Like($likeId));
        dispatch($job);

        return $this->resCreated(true);
    }

    /**
     * 新建相册
     *
     * @Post("/image/createAlbum")
     *
     * @Parameters({
     *      @Parameter("bangumiId", description="所选的番剧 id（bangumi.id）", type="integer", required=true),
     *      @Parameter("creator", description="是否是原创", type="boolean", required=true),
     *      @Parameter("isCartoon", description="是不是漫画", type="boolean", required=true),
     *      @Parameter("name", description="相册名`20字以内`", type="string", required=true),
     *      @Parameter("url", description="封面图片的 url，不包含`host`", type="string", required=true),
     *      @Parameter("width", description="封面图片的宽度", type="integer", required=true),
     *      @Parameter("height", description="封面图片的高度", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "相册对象"}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"})
     * })
     */
    public function createAlbum(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumiId' => 'required|integer',
            'isCartoon' => 'required|boolean',
            'name' => 'string|max:20',
            'url' => 'string',
            'width' => 'required|integer',
            'height' => 'required|integer',
            'creator' => 'required|boolean'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $isCartoon = $request->get('isCartoon');
        $bangumiId = $request->get('bangumiId');

        if ($isCartoon && !$bangumiId)
        {
            return $this->resErrBad('漫画必须选择番剧');
        }

        $name = $request->get('name') ? $request->get('name') : date('y-m-d H:i:s',time());
        $userId = $this->getAuthUserId();

        $image = Image::create([
            'user_id' => $userId,
            'bangumi_id' => $bangumiId,
            'name' => Purifier::clean($name),
            'url' => $request->get('url'),
            'is_cartoon' => $isCartoon,
            'creator' => $request->get('creator'),
            'image_count' => 1,
            'width' => $request->get('width'),
            'height' => $request->get('height'),
            'size_id' => 0
        ]);

        if ($isCartoon)
        {
            $cartoonText = Bangumi::where('id', $bangumiId)->pluck('cartoon')->first();

            Bangumi::where('id', $bangumiId)
                ->update([
                   'cartoon' => $cartoonText ? $cartoonText . ',' . $image['id'] : (String)$image['id']
                ]);
        }
        else
        {
            // 漫画不进审核
            $job = (new \App\Jobs\Trial\Image\Create($image['id']));
            dispatch($job);
        }

        Redis::DEL('user_' . $userId . '_image_albums');
        $transformer = new ImageTransformer();

        return $this->resCreated($transformer->albums([$image->toArray()])[0]);
    }

    /**
     * 编辑相册
     *
     * @Post("/image/editAlbum")
     *
     * @Parameters({
     *      @Parameter("id", description="相册的 id", type="integer", required=true),
     *      @Parameter("bangumiId", description="所选的番剧 id（bangumi.id）", type="integer", required=true),
     *      @Parameter("name", description="相册名`20字以内`", type="string", required=true),
     *      @Parameter("url", description="封面图片的 url，不包含`host`", type="string", required=true),
     *      @Parameter("width", description="封面图片的宽度", type="integer", required=true),
     *      @Parameter("height", description="封面图片的高度", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "相册对象"}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的相册"})
     * })
     */
    public function editAlbum(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'bangumiId' => 'required|integer',
            'name' => 'string|max:20',
            'url' => 'string',
            'width' => 'required|integer',
            'height' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();
        $imageId = $request->get('id');

        $image = Image::whereRaw('id = ? and user_id = ?', [$imageId, $userId])->first();

        if (is_null($image))
        {
            return $this->resErrNotFound();
        }

        if ($request->get('url'))
        {
            Image::where('id', $imageId)
                ->update([
                    'width' => $request->get('width'),
                    'height' => $request->get('height'),
                    'url' => $request->get('url'),
                    'name' => $request->get('name'),
                    'bangumi_id' => $request->get('bangumiId'),
                ]);
        }
        else
        {
            Image::where('id', $imageId)
                ->update([
                    'name' => $request->get('name'),
                    'bangumi_id' => $request->get('bangumiId'),
                ]);
        }

        Redis::DEL('user_image_' . $imageId);
        Redis::DEL('user_image_' . $imageId . '_meta');

        $imageRepository = new ImageRepository();
        $transformer = new ImageTransformer();

        $result = $imageRepository->item($imageId);
        $result['liked'] = false;

        $job = (new \App\Jobs\Trial\Image\Create($imageId));
        dispatch($job);

        return $this->resOK($transformer->waterfall([$result])[0]);
    }

    // TODO：trending service
    // TODO：API Doc
    public function trendingList(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 12;
        $size = intval($request->get('size')) ?: 0;
        $creator = intval($request->get('creator'));
        $bangumiId = intval($request->get('bangumiId'));
        $sort = $request->get('sort') ?: 'new';
        $tags = $request->get('tags') ?: 0;

        $imageRepository = new ImageRepository();

        $ids = Image::whereIn('state', [1, 4])
            ->whereRaw('album_id = ? and image_count <> ?', [0, 1])
            ->whereNotIn('images.id', $seen)
            ->where('is_cartoon', false)
            ->take($take)
            ->when($sort === 'new', function ($query)
            {
                return $query->latest();
            }, function ($query)
            {
                return $query->orderBy('like_count', 'DESC');
            })
            ->when($bangumiId !== -1, function ($query) use ($bangumiId)
            {
                return $query->where('bangumi_id', $bangumiId);
            })
            ->when($creator !== -1, function ($query) use ($creator)
            {
                return $query->where('creator', $creator);
            })
            ->when($size, function ($query) use ($size)
            {
                return $query->where('size_id', $size);
            })
            ->when($tags, function ($query) use ($tags)
            {
                return $query->leftJoin('image_tags AS tags', 'images.id', '=', 'tags.image_id')
                    ->where('tags.tag_id', $tags);
            })
            ->pluck('images.id');

        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'type' => $imageRepository->uploadImageTypes()
            ]);
        }

        $transformer = new ImageTransformer();

        $visitorId = $this->getAuthUserId();
        $list = $imageRepository->list($ids);

        $imageLikeService = new ImageLikeService();
        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $imageLikeService->check($visitorId, $item['id'], $item['user_id']);
            $list[$i]['like_count'] = $imageLikeService->total($item['id']);
        }

        return $this->resOK([
            'list' => $transformer->waterfall($list),
            'type' => $imageRepository->uploadImageTypes()
        ]);
    }

    /**
     * 相册详情
     *
     * @Get("/image/album/`albumId`/show")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "相册信息"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的相册"})
     * })
     */
    public function albumShow($id)
    {
        $album = Image::whereRaw('id = ? and album_id = 0 and image_count > 1', [$id])->first();

        if (is_null($album))
        {
            return $this->resErrNotFound();
        }

        $album = $album->toArray();
        $userId = $this->getAuthUserId();

        $imageRepository = new ImageRepository();
        $userRepository = new UserRepository();

        $user = $userRepository->item($album['user_id']);
        $images = $imageRepository->albumImages($id, $album['images']);

        $userTransformer = new UserTransformer();

        $bangumi = null;
        $bangumiId = $album['bangumi_id'];
        $cartoonList = [];
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->panel($bangumiId, $userId);

        if (is_null($bangumi))
        {
            return null;
        }

        if ($album['is_cartoon'])
        {
            $cartoons = Bangumi::where('id', $bangumiId)->pluck('cartoon')->first();

            $cartoonIds = array_reverse(explode(',', $cartoons));
            foreach ($cartoonIds as $cartoonId)
            {
                $cartoon = Image::where('id', $cartoonId)
                    ->select('id', 'name')
                    ->first();
                if (is_null($cartoon))
                {
                    continue;
                }
                $cartoonList[] = $cartoon->toArray();
            }
        }

        $album['image_count'] = $album['image_count'] - 1;

        $imageLikeService = new ImageLikeService();
        $album['liked'] = $imageLikeService->check($userId, $id, $album['user_id']);
        $album['like_count'] = $imageLikeService->total($id);
        $album['like_users'] = $imageLikeService->users($id);

        $imageViewCounter = new ImageViewCounter();
        $album['view_count'] = $imageViewCounter->add($id);

        $transformer = new ImageTransformer();
        return $this->resOK($transformer->albumShow([
            'user' => $userTransformer->item($user),
            'bangumi' => $bangumi,
            'images' => $images,
            'info' => $album,
            'cartoon' => $cartoonList
        ]));
    }

    /**
     * 相册内图片的排序
     *
     * @Post("/image/album/`albumId`/sort")
     *
     * @Parameters({
     *      @Parameter("result", description="相册内图片排序后的`ids`，用`,`拼接的字符串", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "data": "参数错误"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的相册"})
     * })
     */
    public function albumSort(Request $request, $id)
    {
        $userId = $this->getAuthUserId();
        $album = Image::whereRaw('id = ? and album_id = 0 and image_count > 1 and user_id = ?', [$id, $userId])->first();

        if (is_null($album))
        {
            return $this->resErrNotFound();
        }

        $result = $request->get('result');

        if (!$result)
        {
            return $this->resErrBad();
        }

        Image::where('id', $id)
            ->update([
                'images' => $result
            ]);

        Redis::DEL('image_album_' . $id . '_images');

        return $this->resNoContent();
    }

    /**
     * 删除相册里的图片
     *
     * @Post("/image/album/`albumId`/deleteImage")
     *
     * @Parameters({
     *      @Parameter("result", description="相册内图片排序后的`ids`，用`,`拼接的字符串", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "data": "至少要保留一张图"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的相册"})
     * })
     */
    public function deleteAlbumImage(Request $request, $id)
    {
        $userId = $this->getAuthUserId();
        $album = Image::whereRaw('id = ? and album_id = 0 and image_count > 1 and user_id = ?', [$id, $userId])->first();

        if (is_null($album))
        {
            return $this->resErrNotFound();
        }

        $result = $request->get('result');

        if (!$result)
        {
            return $this->resErrBad('至少要保留一张图');
        }

        Image::where('id', $id)
            ->update([
                'images' => $result
            ]);

        Image::where('id', $id)->increment('image_count', -1);

        Image::whereRaw('id = ? and user_id = ?', [$request->get('id'), $userId])->delete();

        Redis::DEL('image_album_' . $id . '_images');

        $cacheKey = 'user_image_' . $id;
        if (Redis::EXISTS($cacheKey))
        {
            Redis::HINCRBYFLOAT($cacheKey, 'image_count', -1);
        }

        // 不是第一次传图
        $job = (new \App\Jobs\Push\Baidu('album/' . $id, 'update'));
        dispatch($job);

        return $this->resNoContent();
    }

    /**
     * 图片查看大图时的标记
     *
     * @Post("/image/viewedMark")
     *
     * @Parameters({
     *      @Parameter("id", description="图片的 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(204)
     * })
     */
    public function viewedMark(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $imageViewCounter = new ImageViewCounter();
        $imageViewCounter->add($request->get('id'));

        return $this->resNoContent();
    }
}
