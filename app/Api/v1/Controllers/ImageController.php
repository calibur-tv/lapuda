<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Counter\ImageViewCounter;
use App\Api\V1\Services\Counter\Stats\TotalImageAlbumCount;
use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Transformers\ImageTransformer;
use App\Models\AlbumImage;
use App\Models\Banner;
use App\Models\Image;
use App\Services\Geetest\Captcha;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
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

        return $this->resOK($repository->uptoken());
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
        $userId = $this->getAuthUserId();
        Image::where('id', $request->get('id'))
            ->update([
                'state' => $userId ? $userId : time()
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
            'part' => 'required|integer|min:0',
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

        if (!$albumId)
        {
            return $this->resErrBad('图片中可能含有违规信息');
        }

        $totalImageAlbumCount = new TotalImageAlbumCount();
        $totalImageAlbumCount->add();

        $newAlbum = $imageRepository->item($albumId);
        $imageTransformer = new ImageTransformer();

        return $this->resCreated($imageTransformer->userAlbums([$newAlbum])[0]);
    }

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
            'part' => 'required|integer|min:0',
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

        $url = $request->get('url');
        $imageFilter = new ImageFilter();
        $result = $imageFilter->check($url);
        if ($result['delete'])
        {
            return $this->resErrBad('新封面可能含有违规信息');
        }

        $state = 0;
        $name = Purifier::clean($request->get('name'));
        $wordsFilter = new WordsFilter();
        $badWordsCount = $wordsFilter->count($name);
        if ($result['review'] || $badWordsCount)
        {
            $state = $userId;
        }

        Image::where('id', $id)
            ->update([
                'name' => $name,
                'url' => $url,
                'size' => $request->get('size'),
                'type' => $request->get('type'),
                'width' => $request->get('width'),
                'height' => $request->get('height'),
                'part' => $request->get('part'),
                'state' => $state
            ]);

        Redis::DEL($imageRepository->cacheKeyImageItem($id));
        if ($album['is_cartoon'])
        {
            Redis::DEL($imageRepository->cacheKeyCartoonParts($album['bangumi_id']));
        }

        return $this->resNoContent();
    }

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

        if (!$newId)
        {
            return $this->resErrBad('图片中可能含有违规信息');
        }

        $totalImageCount = new TotalImageCount();
        $totalImageCount->add();

        return $this->resCreated($newId);
    }

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

        Redis::DEL($imageRepository->cacheKeyImageItem($imageId));

        return $this->resNoContent();
    }

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

            Image::where('id', $albumId)
                ->update([
                    'image_ids' => implode(',', $newIds)
                ]);
        }

        $job = (new \App\Jobs\Trial\Image\Create($newIds));
        dispatch($job);

        Redis::DEL($imageRepository->cacheKeyImageItem($albumId));
        Redis::DEL($imageRepository->cacheKeyAlbumImages($albumId));

        return $this->resNoContent();
    }

    public function userAlbums()
    {
        $userId = $this->getAuthUserId();

        $albumIds = Image::where('user_id', $userId)
            ->where('is_album', 1)
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

        $list = $imageRepository->userFlow($idsObj['ids']);

        if (empty($list))
        {
            return [
                'list' => [],
                'total' => 0,
                'noMore' => true
            ];
        }

        $imageViewCounter = new ImageViewCounter();
        $imageCommentService = new ImageCommentService();
        $imageLikeService = new ImageLikeService();

        $list = $imageViewCounter->batchGet($list, 'view_count');
        $list = $imageCommentService->batchGetCommentCount($list);
        $list = $imageLikeService->batchTotal($list, 'like_count');

        $imageTransformer = new ImageTransformer();

        return $this->resOK([
            'list' => $imageTransformer->userFlow($list),
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

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

        Image::where('id', $albumId)->delete();
        Redis::DEL($imageRepository->cacheKeyImageItem($albumId));

        if ($album['is_album'])
        {
            AlbumImage::where('album_id', $albumId)->delete();
            Redis::DEL($imageRepository->cacheKeyAlbumImages($albumId));
            if ($albumId['is_cartoon'])
            {
                Redis::DEL($imageRepository->cacheKeyCartoonParts($album['bangumi_id']));
            }
            else
            {
                $totalImageAlbumCount = new TotalImageAlbumCount();
                $totalImageAlbumCount->add(-1);
                $totalImageCount = new TotalImageCount();
                $totalImageCount->add(-count(explode(',', $album['image_ids'])));
            }
        }

        // TODO：SEO
        // TODO：search
        return $this->resNoContent();
    }

    public function show($id)
    {
        $imageRepository = new ImageRepository();
        $image = $imageRepository->item($id);
        if (is_null($image))
        {
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

        $imageLikeService = new ImageLikeService();
        $image['liked'] = $imageLikeService->check($userId, $id, $image['user_id']);
        $image['like_users'] = $imageLikeService->users($id);
        $image['like_total'] = $imageLikeService->total($id);

        $imageViewCounter = new ImageViewCounter();
        $imageViewCounter->add($id);

        $imageTransformer = new ImageTransformer();

        return $this->resOK($imageTransformer->show($image));
    }

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
            return [
                'list' => [],
                'total' => 0,
                'noMore' => true
            ];
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

        Redis::DEL($imageRepository->cacheKeyAlbumImages($id));

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

        Redis::DEL($imageRepository->cacheKeyImageItem($id));
        Redis::DEL($imageRepository->cacheKeyAlbumImages($id));

        $totalImageCount = new TotalImageCount();
        $totalImageCount->add(-1);

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

    public function trialDelete(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $imageRepository = new ImageRepository();

        if ($type === 'image')
        {
            $albumId = AlbumImage::where('id', $id)->pluck('album_id')->first();
            AlbumImage::withTrashed()->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => Carbon::now()
                ]);
            Redis::DEL($imageRepository->cacheKeyAlbumImages($albumId));
        }
        else
        {
            Image::withTrashed()->where('id', $id)
                ->update([
                    'state' => 0,
                    'deleted_at' => Carbon::now()
                ]);
            Redis::DEL($imageRepository->cacheKeyImageItem($id));
        }

        return $this->resNoContent();
    }

    public function trialPass(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');

        if ($type === 'album')
        {
            Image::withTrashed()->where('id', $id)
                ->update([
                    'state' => 0
                ]);
        }
        else
        {
            AlbumImage::withTrashed()->where('id', $id)
                ->update([
                    'state' => 0
                ]);
        }

        return $this->resNoContent();
    }
}
