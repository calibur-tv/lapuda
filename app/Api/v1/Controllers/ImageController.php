<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Activity\BangumiActivity;
use App\Api\V1\Services\Activity\UserActivity;
use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Counter\ImageViewCounter;
use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Toggle\Image\ImageMarkService;
use App\Api\V1\Services\Toggle\Image\ImageRewardService;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Services\UserLevel;
use App\Api\V1\Transformers\ImageTransformer;
use App\Models\AlbumImage;
use App\Models\Banner;
use App\Models\Image;
use App\Models\Looper;
use App\Services\Geetest\Captcha;
use App\Services\OpenSearch\Search;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("图片相关接口")
 */
class ImageController extends Controller
{
    /**
     * 获取PC网页首页banner图
     *
     * @Get("/image/banner")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "图片列表"})
     * })
     */
    public function banner()
    {
        $imageRepository = new ImageRepository();
        $imageTransformer = new ImageTransformer();

        $list = $imageRepository->banners();
        shuffle($list);

        return $this->resOK($imageTransformer->indexBanner($list));
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
        $userId = $this->getAuthUserId();

        return $this->resOK($repository->uptoken($userId));
    }


    /**
     * 新建相册
     *
     * @Post("/image/album/create")
     *
     * @Parameters({
     *      @Parameter("bangumi_id", description="所选的番剧 id", type="integer", required=true),
     *      @Parameter("name", description="相册名称`30字以内`", type="string", required=true),
     *      @Parameter("is_cartoon", description="是不是漫画（只有吧主/管理员才能上传漫画）", type="boolean", required=true),
     *      @Parameter("is_creator", description="是不是原唱（漫画默认都不是原创）", type="boolean", required=true),
     *      @Parameter("url", description="封面图片链接，不包含 host", type="string", required=true),
     *      @Parameter("width", description="图片宽度", type="integer", required=true),
     *      @Parameter("height", description="图片高度", type="integer", required=true),
     *      @Parameter("size", description="图片尺寸", type="string", required=true),
     *      @Parameter("type", description="图片类型", type="string", required=true),
     *      @Parameter("part", description="漫画是第几集，非漫画传0", type="number", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "相册对象"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误，或这一集的漫画已存在，或图片非法"}),
     *      @Response(403, body={"code": 40301, "message": "权限不足（漫画）"})
     * })
     */
    public function createAlbum(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumi_id' => 'required|integer',
            'name' => 'string|max:30',
            'is_cartoon' => 'required|boolean',
            'is_creator' => 'required|boolean',
            'url' => 'required|string',
            'width' => 'required|integer',
            'height' => 'required|integer',
            'size' => 'required|integer',
            'type' => 'required|string',
            'part' => 'required|numeric|min:0',
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $user = $this->getAuthUser();
        if ($user->banned_to)
        {
            return $this->resErrFreeze($user->banned_to);
        }

        $userId = $user->id;
        $isCartoon = $request->get('is_cartoon');
        $bangumiId = $request->get('bangumi_id');
        $part = $request->get('part');
        $name = $request->get('name');
        $url = $request->get('url');

        $imageRepository = new ImageRepository();

        if ($isCartoon)
        {
            $bangumiManager = new BangumiManager();
            if (!$user->is_admin && !$bangumiManager->isOwner($bangumiId, $userId))
            {
                return $this->resErrRole();
            }

            if ($imageRepository->checkHasPartCartoon($bangumiId, $part))
            {
                return $this->resErrBad('已存在的漫画');
            }
        }

        $bangumiFollowService = new BangumiFollowService();
        if (!$bangumiFollowService->check($userId, $bangumiId))
        {
            // 如果没关注番剧，就给他关注
            $bangumiFollowService->do($userId, $bangumiId);
        }

        $albumId = $imageRepository->createSingle([
            'user_id' => $userId,
            'bangumi_id' => $bangumiId,
            'is_cartoon' => $isCartoon,
            'is_creator' => $request->get('is_creator'),
            'is_album' => true,
            'name' => $name,
            'url' => $url,
            'width' => $request->get('width'),
            'height' => $request->get('height'),
            'size' => $request->get('size'),
            'type' => $request->get('type'),
            'part' => $part
        ]);

        $newAlbum = $imageRepository->item($albumId);
        $imageTransformer = new ImageTransformer();

        $userLevel = new UserLevel();
        $exp = $userLevel->change($userId, 3, false);

        $userActivityService = new UserActivity();
        $userActivityService->update($userId, 4);

        $bangumiActivityService = new BangumiActivity();
        $bangumiActivityService->update($bangumiId, 3);

        if ($isCartoon)
        {
            $job = (new \App\Jobs\Push\Baidu('bangumi/' . $bangumiId . '/cartoon', 'update'));
            dispatch($job);
        }

        return $this->resCreated([
            'data' => $imageTransformer->userAlbums([$newAlbum])[0],
            'exp' => $exp,
            'message' => $exp ? "创建成功，经验+{$exp}" : '创建成功'
        ]);
    }

    /**
     * 编辑相册
     *
     * @Post("/image/album/edit")
     *
     * @Parameters({
     *      @Parameter("id", description="相册 id", type="integer", required=true),
     *      @Parameter("name", description="相册名称`30字以内`", type="string", required=true),
     *      @Parameter("url", description="封面图片链接，不包含 host", type="string", required=true),
     *      @Parameter("width", description="图片宽度", type="integer", required=true),
     *      @Parameter("height", description="图片高度", type="integer", required=true),
     *      @Parameter("size", description="图片尺寸", type="string", required=true),
     *      @Parameter("type", description="图片类型", type="string", required=true),
     *      @Parameter("part", description="漫画是第几集，非漫画传0", type="integer", required=true),
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "相册对象"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误，或这一集的漫画已存在，或图片非法"}),
     *      @Response(403, body={"code": 40301, "message": "权限不足（漫画）"})
     * })
     */
    public function editAlbum(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'name' => 'string|max:30',
            'url' => 'required|string',
            'width' => 'required|integer',
            'height' => 'required|integer',
            'size' => 'required|integer',
            'type' => 'required|string',
            'part' => 'required|numeric|min:0',
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $id = $request->get('id');
        $imageRepository = new ImageRepository();
        $album = $imageRepository->item($id);

        if (is_null($album))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        if (!$album['is_cartoon'] && $userId !== $album['user_id'])
        {
            // 不是漫画且不是创建者
            return $this->resErrRole();
        }

        if ($album['is_cartoon'])
        {
            $bangumiManager = new BangumiManager();
            if (!$bangumiManager->isOwner($album['bangumi_id'], $userId))
            {
                // 是漫画但不是吧主
                return $this->resErrRole();
            }
            $part = $request->get('part');
            if ($part != $album['part'] && $imageRepository->checkHasPartCartoon($album['bangumi_id'], $part))
            {
                return $this->resErrBad('已经有这一话了');
            }
        }

        $url = $imageRepository->convertImagePath($request->get('url'));
        $name = Purifier::clean($request->get('name'));

        Image::where('id', $id)
            ->update([
                'name' => $name,
                'url' => $url,
                'size' => $request->get('size'),
                'type' => $request->get('type'),
                'width' => $request->get('width'),
                'height' => $request->get('height'),
                'part' => $request->get('part')
            ]);

        Redis::DEL($imageRepository->itemCacheKey($id));

        $job = (new \App\Jobs\Trial\Image\Create($id));
        dispatch($job);

        return $this->resOK();
    }

    /**
     * 上传单张图片
     *
     * @Post("/image/single/upload")
     *
     * @Parameters({
     *      @Parameter("bangumi_id", description="所选的番剧 id", type="integer", required=true),
     *      @Parameter("name", description="相册名称`30字以内`", type="string", required=true),
     *      @Parameter("is_creator", description="是不是原唱（漫画默认都不是原创）", type="boolean", required=true),
     *      @Parameter("url", description="封面图片链接，不包含 host", type="string", required=true),
     *      @Parameter("width", description="图片宽度", type="integer", required=true),
     *      @Parameter("height", description="图片高度", type="integer", required=true),
     *      @Parameter("size", description="图片尺寸", type="string", required=true),
     *      @Parameter("type", description="图片类型", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "新图片 id"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误，或图片非法"})
     * })
     */
    public function uploadSingleImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumi_id' => 'required|integer',
            'name' => 'string|max:30',
            'is_creator' => 'required|boolean',
            'url' => 'required|string',
            'width' => 'required|integer',
            'height' => 'required|integer',
            'size' => 'required|integer',
            'type' => 'required|string',
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $user = $this->getAuthUser();
        if ($user->banned_to)
        {
            return $this->resErrFreeze($user->banned_to);
        }

        $userId = $user->id;
        $bangumiId = $request->get('bangumi_id');
        $bangumiFollowService = new BangumiFollowService();
        if (!$bangumiFollowService->check($userId, $bangumiId))
        {
            // 如果没关注番剧，就给他关注
            $bangumiFollowService->do($userId, $bangumiId);
        }

        $imageRepository = new ImageRepository();

        $newId = $imageRepository->createSingle([
            'user_id' => $userId,
            'bangumi_id' => $bangumiId,
            'is_album' => false,
            'is_cartoon' => false,
            'is_creator' => $request->get('is_creator'),
            'name' => Purifier::clean($request->get('name')),
            'url' => $imageRepository->convertImagePath($request->get('url')),
            'width' => $request->get('width'),
            'height' => $request->get('height'),
            'size' => $request->get('size'),
            'type' => $request->get('type'),
            'part' => 0
        ]);

        $userLevel = new UserLevel();
        $exp = $userLevel->change($userId, 3, false);

        $userActivityService = new UserActivity();
        $userActivityService->update($userId, 4);

        $bangumiActivityService = new BangumiActivity();
        $bangumiActivityService->update($bangumiId, 3);

        return $this->resCreated([
            'data' => $newId,
            'exp' => $exp,
            'message' => $exp ? "上传成功，经验+{$exp}" : '上传成功'
        ]);
    }

    /**
     * 编辑单张图片
     *
     * @Post("/image/single/edit")
     *
     * @Parameters({
     *      @Parameter("id", description="图片 id", type="integer", required=true),
     *      @Parameter("bangumi_id", description="所选的番剧 id", type="integer", required=true),
     *      @Parameter("name", description="相册名称`30字以内`", type="string", required=true),
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误，或图片非法"}),
     *      @Response(403, body={"code": 40301, "message": "权限不足"})
     * })
     */
    public function editSingleImage(Request $request)
    {
        $imageId = $request->get('id');
        $imageRepository = new ImageRepository();
        $image = $imageRepository->item($imageId);
        if (is_null($image))
        {
            return $this->resErrNotFound();
        }

        if ($image['is_album'])
        {
            return $this->resErrBad();
        }

        $userId = $this->getAuthUserId();
        if ($image['user_id'] !== $userId)
        {
            return $this->resErrRole();
        }

        $bangumiId = $request->get('bangumi_id');
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($bangumiId);
        if (is_null($bangumi))
        {
            return $this->resErrBad();
        }

        $name = Purifier::clean($request->get('name'));
        Image::where('id', $imageId)
            ->update([
                'name' => $name,
                'bangumi_id' => $bangumiId
            ]);

        Redis::DEL($imageRepository->itemCacheKey($imageId));
        $imageRepository->updateProcess($imageId);

        return $this->resOK();
    }

    /**
     * 上传相册内图片
     *
     * > 图片对象示例：
     * 1. `url` 七牛传图后得到的 key，不包含图片地址的 host，如一张图片 https://image.calibur.tv/user/1/avatar.png，七牛返回的 key 是：user/1/avatar.png，将这个 key 传到后端
     * 2. `width` 图片的宽度，七牛上传图片后得到
     * 3. `height` 图片的高度，七牛上传图片后得到
     * 4. `size` 图片的尺寸，七牛上传图片后得到
     * 5. `type` 图片的类型，七牛上传图片后得到
     *
     * @Post("/image/album/upload")
     *
     * @Parameters({
     *      @Parameter("album_id", description="相册 id", type="integer", required=true),
     *      @Parameter("images", description="图片对象数组", type="array", required=true),
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "新图片 id"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"}),
     *      @Response(403, body={"code": 40301, "message": "权限不足，比如不是吧主，却修改漫画"}),
     *      @Response(404, body={"code": 40401, "message": "相册不存在"})
     * })
     */
    public function uploadAlbumImages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'album_id' => 'required|integer',
            'images' => 'required|array',
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $user = $this->getAuthUser();
        if ($user->banned_to)
        {
            return $this->resErrFreeze($user->banned_to);
        }

        $now = Carbon::now();
        $userId = $user->id;
        $images = $request->get('images');
        $albumId = $request->get('album_id');
        $imageRepository = new ImageRepository();

        $saveImages = [];
        foreach ($images as $image)
        {
            $validator = Validator::make($image, [
                'url' => 'required|string',
                'width' => 'required|integer',
                'height' => 'required|integer',
                'size' => 'required|integer',
                'type' => 'required|string',
            ]);

            if ($validator->fails())
            {
                return $this->resErrParams($validator);
            }

            $saveImages[] = [
                'url' => $imageRepository->convertImagePath($image['url']),
                'width' => $image['width'],
                'height' => $image['height'],
                'size' => $image['size'],
                'type' => $image['type'],
                'user_id' => $userId,
                'album_id' => $albumId,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        $album = $imageRepository->item($albumId);

        if (is_null($album) || $album['user_id'] != $userId)
        {
            return $this->resErrNotFound();
        }

        if ($album['is_cartoon'])
        {
            $bangumiManager = new BangumiManager();
            if (!$bangumiManager->isOwner($album['bangumi_id'], $userId))
            {
                return $this->resErrRole();
            }
        }

        AlbumImage::insert($saveImages);
        $nowIds = AlbumImage
            ::where('album_id', $albumId)
            ->pluck('id')
            ->toArray();

        if ($album['image_count'])
        {
            $imageIds = Image
                ::where('id', $albumId)
                ->pluck('image_ids')
                ->first();

            $oldIds = explode(',', $imageIds);
            $newIds = array_diff($nowIds, $oldIds);

            Image::where('id', $albumId)
                ->update([
                    'image_ids' => $imageIds . ',' . implode(',', $newIds)
                ]);
        }
        else
        {
            $newIds = $nowIds;

            Image
                ::where('id', $albumId)
                ->update([
                    'image_ids' => implode(',', $newIds)
                ]);

            $imageTrendingService = new ImageTrendingService($album['bangumi_id'], $album['user_id']);
            $imageTrendingService->delete($albumId);
            $imageTrendingService->create($albumId, !$album['is_cartoon']);
        }

        if ($album['is_cartoon'])
        {
            $totalImageCount = new TotalImageCount();
            $totalImageCount->add(count($saveImages));
        }
        else
        {
            $job = (new \App\Jobs\Trial\Image\Append($newIds));
            dispatch($job);
        }

        $userActivityService = new UserActivity();
        $userActivityService->update($userId, 3);

        Redis::DEL($imageRepository->itemCacheKey($albumId));
        Redis::DEL($imageRepository->cacheKeyAlbumImages($albumId));
        $imageRepository->updateProcess($albumId);

        return $this->resOK();
    }

    /**
     * 自己的相册列表
     *
     * @Get("/image/album/users")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": "相册数组"})
     * })
     */
    public function userAlbums()
    {
        $userId = $this->getAuthUserId();

        $albumIds = Image::where('user_id', $userId)
            ->where('is_album', 1)
            ->where('state', 0)
            ->pluck('id')
            ->toArray();

        if (empty($albumIds))
        {
            return $this->resOK([]);
        }
        $imageRepository = new ImageRepository();

        $list = $imageRepository->bangumiFlow($albumIds);
        $imageTransformer = new ImageTransformer();

        return $this->resOK($imageTransformer->userAlbums($list));
    }

    /**
     * 删除某个相册
     *
     * @Post("/image/album/delete")
     *
     * @Parameters({
     *      @Parameter("id", description="相册 id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(403, body={"code": 40301, "message": "权限不足"}),
     *      @Response(404, body={"code": 40401, "message": "相册不存在"})
     * })
     */
    public function deleteAlbum(Request $request)
    {
        $albumId = $request->get('id');
        $imageRepository = new ImageRepository();
        $album = $imageRepository->item($albumId);
        if (is_null($album))
        {
            return $this->resErrNotFound();
        }

        $user = $this->getAuthUser();
        $userId = $user->id;
        if (!$user->is_admin)
        {
            if ($album['is_cartoon'])
            {
                $bangumiManager = new BangumiManager();
                if (!$bangumiManager->isOwner($album['bangumi_id'], $userId))
                {
                    return $this->resErrRole();
                }
            }
            if ($userId !== $album['user_id'])
            {
                return $this->resErrRole();
            }
        }

        $exp = $imageRepository->deleteProcess($albumId);

        return $this->resOK([
            'exp' => $exp,
            'message' => "删除成功，经验{$exp}"
        ]);
    }

    /**
     * 图片详情页
     *
     * @Get("/image/${image_id}/show")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "相册页面信息"}),
     *      @Response(404, body={"code": 40401, "message": "图片不存在"}),
     *      @Response(423, body={"code": 42301, "message": "内容正在审核中"})
     * })
     */
    public function show(Request $request, $id)
    {
        $imageRepository = new ImageRepository();
        $image = $imageRepository->item($id, true);
        if (is_null($image))
        {
            return $this->resErrNotFound();
        }

        if ($image['deleted_at'] && !$request->get('showDelete'))
        {
            if ($image['state'])
            {
                return $this->resErrLocked();
            }

            return $this->resErrNotFound();
        }

        $userRepository = new UserRepository();
        $user = $userRepository->item($image['user_id']);
        if (is_null($user))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->panel($image['bangumi_id'], $userId);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $image['bangumi'] = $bangumi;
        $image['user'] = $user;
        $image['images'] = $image['is_album'] ? $imageRepository->albumImages($id) : [];
        $image['parts'] = $image['is_cartoon'] ? $imageRepository->getCartoonParts($image['bangumi_id']) : [];

        if ($image['is_creator'])
        {
            $imageRewardService = new ImageRewardService();
            $image['rewarded'] = $imageRewardService->check($userId, $id);
            $image['reward_users'] = $imageRewardService->users($id);
        }
        else
        {
            $image['rewarded'] = false;
            $image['reward_users'] = [
                'list' => [],
                'total' => 0,
                'noMore' => true
            ];
        }

        $imageLikeService = new ImageLikeService();
        $imageMarkService = new ImageMarkService();
        $image['liked'] = $imageLikeService->check($userId, $id);
        $image['like_users'] = $imageLikeService->users($id);
        $image['marked'] = $imageMarkService->check($userId, $id);
        $image['mark_users'] = $imageMarkService->users($id);

        $imageViewCounter = new ImageViewCounter();
        $image['view_count'] = $imageViewCounter->add($id);

        $imageTransformer = new ImageTransformer();

        $searchService = new Search();
        if ($searchService->checkNeedMigrate('image', $id))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('image', $id));
            dispatch($job);
        }
        $shareData = [
            'title' => $image['name'] ?: '来自calibur分享的相册~',
            'desc' => "《{$bangumi['name']}》",
            'link' => "https://m.calibur.tv/pin/{$image['id']}",
            'image' => $image['url'] . '-share120jpg'
        ];
        $image['share_data'] = $shareData;

        return $this->resOK($imageTransformer->show($image));
    }

    /**
     * 番剧漫画列表
     *
     * @Get("/bangumi/${bangumi_id}/cartoon")
     *
     * @Parameters({
     *      @Parameter("take", description="取的格式", type="integer", required=true, default=12),
     *      @Parameter("page", description="页数", type="integer", required=true, default=0),
     *      @Parameter("sort", description="升降序，desc 或者 asc", type="string", required=true, default="desc"),
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"list":"漫画列表", "total": "总数", "noMore": "是否还有更多"}})
     * })
     */
    public function cartoon(Request $request, $id)
    {
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 12;
        $sort = $request->get('sort') ?: 'desc';
        $sort = $sort === 'desc' ? 'desc' : ($sort === 'asc' ? 'asc' : 'desc');

        $imageRepository = new ImageRepository();
        $idsObj = $imageRepository->getBangumiCartoonIds($id, $page, $take, $sort);
        if (is_null($idsObj))
        {
            return $this->resOK([
                'list' => [],
                'total' => 0,
                'noMore' => true
            ]);
        }

        $list = $imageRepository->bangumiFlow($idsObj['ids']);
        $imageViewCounter = new ImageViewCounter();
        $imageCommentService = new ImageCommentService();
        $imageLikeService = new ImageLikeService();

        $list = $imageViewCounter->batchGet($list, 'view_count');
        $list = $imageCommentService->batchGetCommentCount($list);
        $list = $imageLikeService->batchTotal($list, 'like_count');

        $imageTransformer = new ImageTransformer();

        return $this->resOK([
            'list' => $imageTransformer->cartoon($list),
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
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
        $imageRepository = new ImageRepository();
        $album = $imageRepository->item($id);

        if (is_null($album))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        if (!$album['is_cartoon'] && $userId !== $album['user_id'])
        {
            // 不是漫画且不是创建者
            return $this->resErrRole();
        }

        if ($album['is_cartoon'])
        {
            $bangumiManager = new BangumiManager();
            if (!$bangumiManager->isOwner($album['bangumi_id'], $userId))
            {
                // 是漫画但不是吧主
                return $this->resErrRole();
            }
        }

        $result = $request->get('result');

        $newIds = explode(',', $result);
        if (!$result || $album['image_count'] !== count($newIds))
        {
            // 排序前后的个数不一致
            return $this->resErrBad();
        }

        $imageIds = Image::where('id', $id)
            ->pluck('image_ids')
            ->first();
        if (count(array_diff($newIds, explode(',', $imageIds))))
        {
            // 包含了非本相册的图片
            return $this->resErrRole();
        }

        Image::where('id', $id)
            ->update([
                'image_ids' => $result
            ]);

        Redis::DEL($imageRepository->itemCacheKey($id));
        Redis::DEL($imageRepository->cacheKeyAlbumImages($id));

        return $this->resOK();
    }

    /**
     * 删除相册里的图片
     *
     * @Post("/image/album/`albumId`/deleteImage")
     *
     * @Parameters({
     *      @Parameter("result", description="相册内图片排序后的`ids`，用`,`拼接的字符串", type="string", required=true),
     *      @Parameter("imageId", description="要删除的图片id", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(400, body={"code": 40003, "data": "请求参数错误"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的相册，或要删除的图片已经被删除"})
     * })
     */
    public function deleteAlbumImage(Request $request, $id)
    {
        $imageRepository = new ImageRepository();
        $album = $imageRepository->item($id);
        if (is_null($album))
        {
            return $this->resErrNotFound();
        }

        $image = AlbumImage::find($request->get('imageId'));
        if (is_null($image))
        {
            return $this->resErrNotFound();
        }

        $user = $this->getAuthUser();
        $userId = $user->id;

        if (!$user->is_admin)
        {
            if ($album['is_cartoon'])
            {
                $bangumiManager = new BangumiManager();
                if (!$bangumiManager->isOwner($album['bangumi_id'], $userId))
                {
                    return $this->resErrRole();
                }
            }
            else if ($album['user_id'] !== $userId || $image->user_id != $userId || $image->album_id != $id)
            {
                return $this->resErrRole();
            }
        }

        $result = $request->get('result');

        $newIds = explode(',', $result);
        if (count($newIds) >= $album['image_count'])
        {
            return $this->resErrBad();
        }

        $imageIds = Image::where('id', $id)
            ->pluck('image_ids')
            ->first();
        if (count(array_diff($newIds, explode(',', $imageIds))))
        {
            // 包含了非本相册的图片
            return $this->resErrRole();
        }

        Image::where('id', $id)
            ->update([
                'image_ids' => $result
            ]);

        $image->delete();

        Redis::DEL($imageRepository->itemCacheKey($id));
        Redis::DEL($imageRepository->cacheKeyAlbumImages($id));

        $totalImageCount = new TotalImageCount();
        $totalImageCount->add(-1);

        return $this->resOK();
    }

    // 后台待审图片列表
    public function trials()
    {
        $albums = Image::withTrashed()
            ->where('state', '<>', 0)
            ->get()
            ->toArray();

        $images = AlbumImage::withTrashed()
            ->where('state', '<>', 0)
            ->get()
            ->toArray();

        return $this->resOK(array_merge($albums, $images));
    }

    // 后台删除图片
    public function ban(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $imageRepository = new ImageRepository();

        if ($type === 'album')
        {
            $imageRepository->deleteProcess($id);
        }
        else
        {
            $image = DB
                ::table('album_images')
                ->where('id', $id)
                ->first();

            DB::table('album_images')
                ->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => Carbon::now()
                ]);

            $totalImageCount = new TotalImageCount();
            $totalImageCount->add(-1);
            Redis::DEL($imageRepository->itemCacheKey($image->album_id));
            Redis::DEL($imageRepository->cacheKeyAlbumImages($image->album_id));
        }

        return $this->resNoContent();
    }

    // 后台通过图片
    public function pass(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');

        if ($type === 'album')
        {
            $imageRepository = new ImageRepository();
            $imageRepository->recoverProcess($id);
        }
        else
        {
            DB::table('album_images')
                ->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => null
                ]);

            $totalImageCount = new TotalImageCount();
            $totalImageCount->add();
        }

        return $this->resNoContent();
    }

    // 后台确认删除
    public function approve(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');

        if ($type === 'album')
        {
            DB::table('images')
                ->where('id', $id)
                ->update([
                    'state' => 0
                ]);

            Redis::DEL('image_' . $id);
        }
        else
        {
            DB::table('album_images')
                ->where('id', $id)
                ->update([
                    'state' => 0
                ]);
        }

        return $this->resNoContent();
    }

    // 后台驳回删除
    public function reject(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');

        if ($type === 'album')
        {
            DB::table('images')
                ->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => null
                ]);

            Redis::DEL('image_' . $id);

            $imageRepository = new ImageRepository();
            $imageRepository->createProcess($id);
        }
        else
        {
            DB::table('album_images')
                ->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => null
                ]);

            $totalImageCount = new TotalImageCount();
            $totalImageCount->add();
        }

        return $this->resNoContent();
    }

    // 后台获取所有的 banner 图
    public function getIndexBanners()
    {
        $imageRepository = new ImageRepository();

        $list = $imageRepository->banners(true);
        $transformer = new ImageTransformer();

        return $this->resOK($transformer->indexBanner($list));
    }

    // 后台更新 banner
    public function uploadIndexBanner(Request $request)
    {
        $now = Carbon::now();

        $id = Banner::insertGetId([
            'url' => $request->get('url'),
            'bangumi_id' => $request->get('bangumi_id') ?: 0,
            'user_id' => $request->get('user_id') ?: 0,
            'gray' => $request->get('gray') ?: 0,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        Redis::DEL('loop_banners');
        Redis::DEL('loop_banners_all');

        return $this->resCreated($id);
    }

    // 后台上下线 banner
    public function toggleIndexBanner(Request $request)
    {
        $id = $request->get('id');
        $banner = Banner::find($id);

        if (is_null($banner))
        {
            Banner::withTrashed()->find($id)->restore();
            $result = true;
        }
        else
        {
            $banner->delete();
            $result = false;
        }

        Redis::DEL('loop_banners');
        Redis::DEL('loop_banners_all');

        return $this->resOK($result);
    }

    // 编辑 banner 信息
    public function editIndexBanner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
            'bangumi_id' => 'required|integer|min:0',
            'user_id' => 'required|integer|min:0'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $id = $request->get('id');

        Banner::withTrashed()
            ->where('id', $id)
            ->update([
                'bangumi_id' => $request->get('bangumi_id'),
                'user_id' => $request->get('user_id'),
                'updated_at' => Carbon::now()
            ]);

        Redis::DEL('loop_banners');
        Redis::DEL('loop_banners_all');

        return $this->resNoContent();
    }

    // 删除相册的封面
    public function deleteAlbumPoster(Request $request)
    {
        $id = $request->get('id');

        $imageRepository = new ImageRepository();

        $list = $imageRepository->albumImages($id);
        if (empty($list))
        {
            return $this->resNoContent();
        }

        $item = $list[0];
        Image::where('id', $id)
            ->update([
                'url' => $imageRepository->convertImagePath($item['url']),
                'width' => $item['width'],
                'height' => $item['height'],
                'size' => $item['size'],
                'type' => $item['type']
            ]);

        Redis::DEL($imageRepository->itemCacheKey($id));

        return $this->resNoContent();
    }
}
