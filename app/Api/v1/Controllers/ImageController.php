<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Counter\ImageViewCounter;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Transformers\ImageTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\AlbumImage;
use App\Models\Bangumi;
use App\Models\Banner;
use App\Models\Image;
use App\Models\ImageTag;
use App\Services\Geetest\Captcha;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

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
            'part' => 'required|integer',
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $user = $this->getAuthUser();
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

        $imageTransformer = new ImageTransformer();
        $album = [
            'id' => $albumId,
            'bangumi_id' => $bangumiId,
            'name' => $name,
            'url' => $url
        ];

        return $this->resCreated($imageTransformer->choiceUserAlbum([$album])[0]);
    }

    public function uploadImage(Request $request)
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

        $userId = $this->getAuthUserId();
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
            'name' => $request->get('name'),
            'url' => $request->get('url'),
            'width' => $request->get('width'),
            'height' => $request->get('height'),
            'size' => $request->get('size'),
            'type' => $request->get('type'),
            'part' => 0
        ]);

        return $this->resCreated($newId);
    }

    public function uploadAlbumImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'album_id' => 'required|integer',
            'images' => 'required|array',
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $now = Carbon::now();
        $userId = $this->getAuthUserId();
        $images = $request->get('images');
        $albumId = $request->get('album_id');

        foreach ($images as $i => $image)
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

            $images[$i]['user_id'] = $userId;
            $images[$i]['album_id'] = $albumId;
            $images[$i]['created_at'] = $now;
            $images[$i]['updated_at'] = $now;
        }

        $imageRepository = new ImageRepository();
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

        AlbumImage::insert($images);
        $nowIds = AlbumImage::where('album_id', $albumId)
            ->pluck('id')
            ->toArray();

        if ($album['image_count'])
        {
            $imageIds = Image::where('id', $albumId)
                ->pluck('image_ids');

            $oldIds = explode(',', $imageIds);
            $newIds = array_diff($nowIds, $oldIds);

            Image::where('id', $albumId)
                ->update([
                    'image_ids' => $imageIds . ',' . implode(',', $newIds)
                ]);
        }
        else
        {
            Image::where('id', $albumId)
                ->update([
                    'image_ids' => implode(',', $nowIds)
                ]);
        }

        // TODO：review images process，先发后审

        return $this->resNoContent();
    }

    public function userAlbums()
    {
        $userId = $this->getAuthUserId();

        $albums = Image::where('user_id', $userId)
            ->where('is_album', 1)
            ->select('id', 'name', 'bangumi_id', 'url')
            ->get()
            ->toArray();

        $imageTransformer = new ImageTransformer();

        return $this->resOK($imageTransformer->choiceUserAlbum($albums));
    }

    public function users(Request $request)
    {
        $page = $request->get('page') ?: 0;
        $zone = $request->get('zone');
        $take = 12;

        $imageRepository = new ImageRepository();
        $userId = $imageRepository->getUserIdByZone($zone);

        if (!$userId)
        {
            return $this->resErrNotFound();
        }

        $idsObj = $imageRepository->getUserImageIds($userId, $page, $take);
        $list = $imageRepository->list($idsObj['ids']);
        $imageViewCounter = new ImageViewCounter();
        $imageCommentService = new ImageCommentService();
        $imageLikeService = new ImageLikeService();

        $list = $imageViewCounter->batchGet($list, 'view_count');
        $list = $imageCommentService->batchGetCommentCount($list);
        $list = $imageLikeService->batchTotal($list, 'like_count');

        return $this->resOK([
            'list' => $list,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

    public function bangumiActive(Request $request)
    {

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

    public function show($id)
    {
        $imageRepository = new ImageRepository();
        $image = $imageRepository->item($id);
        if (is_null($image))
        {
            return $this->resErrNotFound();
        }
        if ($image['is_album'])
        {
            $image['images'] = $imageRepository->albumImages($id);
        }
        $userId = $this->getAuthUserId();

        $bangumiRepository = new BangumiRepository();
        $image['bangumi'] = $bangumiRepository->panel($image['bangumi_id'], $userId);

        $imageLikeService = new ImageLikeService();
        $image['liked'] = $imageLikeService->check($userId, $id, $image['user_id']);
        $image['like_users'] = $imageLikeService->users($id);
        $image['like_total'] = $imageLikeService->total($id);

        $imageViewCounter = new ImageViewCounter();
        $imageViewCounter->add($id);

        return $this->resOK($image);
    }

    public function trendingNews(Request $request)
    {
        $minId = intval($request->get('minId')) ?: 0;
        $take = 12;

        $userId = $this->getAuthUserId();
        $imageTrendingService = new ImageTrendingService($userId);

        return $this->resOK($imageTrendingService->news($minId, $take));
    }

    public function trendingActive(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = 12;

        $userId = $this->getAuthUserId();
        $imageTrendingService = new ImageTrendingService($userId);

        return $this->resOK($imageTrendingService->active($seen, $take));
    }

    public function trendingHot(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = 12;

        $userId = $this->getAuthUserId();
        $imageTrendingService = new ImageTrendingService($userId);

        return $this->resOK($imageTrendingService->hot($seen, $take));
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

    public function getIndexBanners()
    {
        $imageRepository = new ImageRepository();

        $list = $imageRepository->banners(true);
        $transformer = new ImageTransformer();

        return $this->resOK($transformer->indexBanner($list));
    }

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

    public function trialList()
    {
        $images = Image::withTrashed()->whereIn('state', [2, 4])->get();

        return $this->resOK($images);
    }

    public function trialDelete(Request $request)
    {
        $id = $request->get('id');
        $image = Image::withTrashed()
            ->find($id);

        if (is_null($image))
        {
            return $this->resErrNotFound();
        }

        if ($image->image_count == 0)
        {
            $image->update([
                'state' => 3
            ]);

            $image->delete();
        }
        else
        {
            $image->update([
                'state' => 1,
                'url' => ''
            ]);
        }

        return $this->resNoContent();
    }

    public function trialPass(Request $request)
    {
        $id = $request->get('id');

        Image::withTrashed()->where('id', $id)
            ->update([
                'state' => 1,
                'deleted_at' => null
            ]);

        Redis::DEL('user_image_' . $id);

        return $this->resNoContent();
    }
}
