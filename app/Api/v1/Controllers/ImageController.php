<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\ImageTransformer;
use App\Models\Image;
use App\Models\ImageLike;
use App\Models\ImageTag;
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
            'size_id' => $request->get('size'),
            'creator' => $request->get('creator'),
            'created_at' => $now,
            'updated_at' => $now
        ]);

        ImageTag::create([
            'image_id' => $id,
            'tag_id' => $request->get('tags')
        ]);

        $cacheKey = 'user_' . $userId . '_image_ids';
        if (Redis::EXISTS($cacheKey))
        {
            Redis::LPUSH($cacheKey, $id);
        }

        $job = (new \App\Jobs\Trial\Image\Create($id));
        dispatch($job);

        return $this->resCreated($id);
    }

    public function edit(Request $request)
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

        return $this->resNoContent();
    }

    public function delete(Request $request)
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
            ->whereNotIn('id', $seen)
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

        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();
        $transformer = new ImageTransformer();

        $userId = $this->getAuthUserId();
        $list = $imageRepository->list($ids);

        foreach ($list as $i => $item)
        {
            $list[$i]['bangumi'] = $list[$i]['bangumi_id'] ? $bangumiRepository->item($item['bangumi_id']) : null;
            $list[$i]['liked'] = $userId ? $imageRepository->checkLiked($item['id'], $userId) : false;
            $list[$i]['user'] = $userRepository->item($item['user_id']);
        }

        return $this->resOK([
            'list' => $transformer->trending($list),
            'type' => $imageRepository->uploadImageTypes()
        ]);
    }
}
