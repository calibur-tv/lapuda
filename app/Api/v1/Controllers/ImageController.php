<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\ImageTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Image;
use App\Models\ImageLike;
use App\Models\ImageTag;
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
            'images' => 'required|array',
            'size' => 'required|integer',
            'tags' => 'required|integer',
            'roleId' => 'required|integer',
            'albumId' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $userId = $this->getAuthUserId();
        $albumId = $request->get('albumId');
        $images = $request->get('images');

        $now = Carbon::now();
        $ids = [];

        foreach ($images as $item)
        {
            $id = Image::insertGetId([
                'user_id' => $userId,
                'bangumi_id' => $request->get('bangumiId'),
                'url' => $item['key'],
                'width' => $item['width'],
                'height' => $item['height'],
                'role_id' => $request->get('roleId'),
                'size_id' => $request->get('size'),
                'creator' => $request->get('creator'),
                'image_count' => 0,
                'album_id' => $albumId,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            ImageTag::create([
                'image_id' => $id,
                'tag_id' => $request->get('tags')
            ]);

            $ids[] = $id;

            $job = (new \App\Jobs\Trial\Image\Create($id));
            dispatch($job);
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
            }
            else
            {
                Image::where('id', $albumId)
                    ->update([
                        'images' => $images . ',' . implode(',', $ids)
                    ]);
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

    public function editImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumiId' => 'required|integer',
            'size' => 'required|integer',
            'tags' => 'required|integer',
            'roleId' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
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

    public function report(Request $request)
    {
        Image::where('id', $request->get('id'))
            ->update([
                'state' => 4
            ]);

        return $this->resNoContent();
    }

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

        $liked = ImageLike::whereRaw('user_id = ? and image_id = ?', [$userId, $imageId])->count();
        $isCreator = (boolean)$image['creator'];
        $userRepository = new UserRepository();

        if ($liked)
        {
            if ($isCreator)
            {
                $result = $userRepository->toggleCoin(true, $userId, $image['user_id'], 4, $imageId);

                if (!$result)
                {
                    return $this->resErrRole('没有点赞记录');
                }
            }

            ImageLike::whereRaw('user_id = ? and image_id = ?', [$userId, $imageId])->delete();
            Image::where('id', $imageId)->increment('like_count', -1);

            if (Redis::EXISTS('user_image_'.$imageId))
            {
                Redis::HINCRBYFLOAT('user_image_'.$imageId, 'like_count', -1);
            }

            return $this->resOK(false);
        }

        if ($isCreator)
        {
            $success = $userRepository->toggleCoin(false, $userId, $image['user_id'], 4, $imageId);

            if (!$success)
            {
                return $this->resErrRole('金币不足');
            }
        }

        $now = Carbon::now();
        $likeId = ImageLike::insertGetId([
            'user_id' => $userId,
            'image_id' => $imageId,
            'created_at' => $now,
            'updated_at' => $now
        ]);
        Image::where('id', $imageId)->increment('like_count', 1);

        if (Redis::EXISTS('user_image_'.$imageId))
        {
            Redis::HINCRBYFLOAT('user_image_'.$imageId, 'like_count', 1);
        }

        $job = (new \App\Jobs\Notification\Image\Like($likeId));
        dispatch($job);

        return $this->resOK(true);
    }

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
            return $this->resErrParams($validator->errors());
        }

        $name = $request->get('name') ? $request->get('name') : date('y-m-d H:i:s',time());
        $userId = $this->getAuthUserId();

        $image = Image::create([
            'user_id' => $userId,
            'bangumi_id' => $request->get('bangumiId'),
            'name' => Purifier::clean($name),
            'url' => $request->get('url'),
            'is_cartoon' => $request->get('isCartoon'),
            'creator' => $request->get('creator'),
            'image_count' => 1,
            'width' => $request->get('width'),
            'height' => $request->get('height'),
            'size_id' => 0
        ]);

        Redis::DEL('user_' . $userId . '_image_albums');
        $transformer = new ImageTransformer();

        $job = (new \App\Jobs\Trial\Image\Create($image['id']));
        dispatch($job);

        return $this->resCreated($transformer->albums([$image->toArray()])[0]);
    }

    public function editAlbum(Request $request)
    {
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

    public function trendingList(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 12;
        $size = intval($request->get('size')) ?: 0;
        $tags = $request->get('tags') ?: 0;
        $creator = $request->get('creator');
        $sort = $request->get('sort') ?: 'new';
        $bangumiId = $request->get('bangumiId');

        $imageRepository = new ImageRepository();

        $ids = Image::whereIn('state', [1, 4])
            ->whereRaw('album_id = ? and image_count <> ?', [0, 1])
            ->whereNotIn('images.id', $seen)
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

        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $imageRepository->checkLiked($item['id'], $visitorId, $item['user_id']);
        }

        return $this->resOK([
            'list' => $transformer->waterfall($list),
            'type' => $imageRepository->uploadImageTypes()
        ]);
    }

    public function albumShow($id)
    {
        $album = Image::whereRaw('id = ? and album_id = 0 and image_count > 1', [$id])->first();

        if (is_null($album))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();

        $imageRepository = new ImageRepository();
        $userRepository = new UserRepository();

        $user = $userRepository->item($album['user_id']);
        $images = $imageRepository->albumImages($id, $album['images']);

        $userTransformer = new UserTransformer();
        $imageTransformer = new ImageTransformer();

        $bangumi = null;
        $bangumiId = $album['bangumi_id'];
        if ($bangumiId)
        {
            $bangumiRepository = new BangumiRepository();
            $bangumi = $bangumiRepository->item($bangumiId);

            $bangumi['followed'] = $bangumiRepository->checkUserFollowed($userId, $bangumiId);

            $bangumiTransformer = new BangumiTransformer();
            $bangumi = $bangumiTransformer->album($bangumi);
        }

        return $this->resOK([
            'user' => $userTransformer->item($user),
            'bangumi' => $bangumi,
            'images' => $imageTransformer->albumShow($images),
            'liked' => $imageRepository->checkLiked($id, $userId, $album['user_id']),
            'name' => $album['name'],
            'poster' => $album['url']
        ]);
    }
}
