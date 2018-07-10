<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/23
 * Time: 上午10:08
 */

namespace App\Api\V1\Repositories;

use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Transformers\ImageTransformer;
use App\Models\AlbumImage;
use App\Models\Banner;
use App\Models\Image;
use App\Models\Tag;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

class ImageRepository extends Repository
{
    public function createSingle($params)
    {
        $now = Carbon::now();

        $newId = DB::table('images')
            ->insertGetId([
                'user_id' => $params['user_id'],
                'bangumi_id' => $params['bangumi_id'],
                'is_cartoon' => $params['is_cartoon'],
                'is_creator' => $params['is_creator'],
                'is_album' => $params['is_album'],
                'name' => Purifier::clean($params['name']),
                'url' => $params['url'],
                'width' => $params['width'],
                'height' => $params['height'],
                'size' => $params['size'],
                'type' => $params['type'],
                'part' => $params['part'],
                'created_at' => $now,
                'updated_at' => $now
            ]);

        $imageFilter = new ImageFilter();
        $result = $imageFilter->check($params['url']);
        if ($result['delete'])
        {
            DB::table('images')
                ->where('id', $newId)
                ->update([
                    'state' => $params['user_id'],
                    'deleted_at' => $now
                ]);

            return 0;
        }

        $wordsFilter = new WordsFilter();
        $badWordsCount = $wordsFilter->count($params['name']);

        if ($result['review'] || $badWordsCount > 0)
        {
            DB::table('images')
                ->where('id', $newId)
                ->update([
                    'state' => $params['user_id']
                ]);
        }

        if ($params['is_cartoon'])
        {
            Redis::DEL($this->cacheKeyCartoonParts($params['bangumi_id']));
        }

        $this->imageCreateSuccessProcess($newId, $params['user_id'], $params['bangumi_id']);

        return $newId;
    }

    public function uptoken()
    {
        $auth = new \App\Services\Qiniu\Auth();
        $timeout = 3600;
        $uptoken = $auth->uploadToken(null, $timeout, [
            'returnBody' => '{
                "code": 0,
                "data": {
                    "height": $(imageInfo.height),
                    "width": $(imageInfo.width),
                    "type": "$(mimeType)",
                    "size": $(fsize),
                    "key": "$(key)"
                }
            }',
            'mimeLimit' => 'image/jpeg;image/png;image/jpg;image/gif'
        ]);

        return [
            'upToken' => $uptoken,
            'expiredAt' => time() + $timeout
        ];
    }

    public function banners($withTrashed = false)
    {
        $list = $this->RedisList($withTrashed ? 'loop_banners_all' : 'loop_banners', function () use ($withTrashed)
        {
            $list = $withTrashed
                ? Banner::withTrashed()
                    ->select('id', 'url', 'user_id', 'bangumi_id', 'gray', 'deleted_at')
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->toArray()
                : Banner::select('id', 'url', 'user_id', 'bangumi_id', 'gray')->get()->toArray();

            $userRepository = new UserRepository();
            $bangumiRepository = new BangumiRepository();

            foreach ($list as $i => $image)
            {
                if ($image['user_id'])
                {
                    $user = $userRepository->item($image['user_id']);
                    $list[$i]['user_nickname'] = $user['nickname'];
                    $list[$i]['user_avatar'] = $user['avatar'];
                    $list[$i]['user_zone'] = $user['zone'];
                }
                else
                {
                    $list[$i]['user_nickname'] = '';
                    $list[$i]['user_avatar'] = '';
                    $list[$i]['user_zone'] = '';
                }

                if ($image['bangumi_id'])
                {
                    $bangumi = $bangumiRepository->item($image['bangumi_id']);
                    $list[$i]['bangumi_name'] = $bangumi['name'];
                }
                else
                {
                    $list[$i]['bangumi_name'] = '';
                }

                $list[$i] = json_encode($list[$i]);
            }

            return $list;
        });

        $result = [];
        foreach ($list as $item)
        {
            $result[] = json_decode($item, true);
        }

        return $result;
    }

    public function uploadImageTypes()
    {
        return $this->Cache('upload-image-types', function ()
        {
            $size = Tag::where('model', '2')->select('name', 'id')->get();
            $tags = Tag::where('model', '1')->select('name', 'id')->get();

            return [
                'size' => $size,
                'tags' => $tags
            ];
        }, 'm');
    }

    public function item($id)
    {
        if (!$id)
        {
            return null;
        }

        return $this->Cache($this->cacheKeyImageItem($id), function () use ($id)
        {
            $image = Image::find($id);

            if (is_null($image))
            {
                return null;
            }

            $image = $image->toArray();
            $userRepository = new UserRepository();
            $user = $userRepository->item($image['user_id']);

            if (is_null($user))
            {
                return null;
            }
            $image['user'] = $user;

            $bangumiRepository = new BangumiRepository();
            $bangumi = $bangumiRepository->item($image['bangumi_id']);

            if (is_null($bangumi))
            {
                return null;
            }
            $image['bangumi'] = $bangumi;

            $image['image_count'] = $image['is_album'] == 1
                ? is_null($image['image_ids']) ? 0 : count(explode(',', $image['image_ids']))
                : 1;

            $imageTransformer = new ImageTransformer();

            return $imageTransformer->show($image);
        });
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->item($id);
            if ($item)
            {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function albumImages($albumId)
    {
        if (!$albumId)
        {
            return [];
        }

        return $this->Cache($this->cacheKeyAlbumImages($albumId), function () use ($albumId)
        {
            $imageIds = Image::where('id', $albumId)
                ->pluck('image_ids')
                ->first();

            if (is_null($imageIds))
            {
                return [];
            }

            $ids = explode(',', $imageIds);
            $images = [];
            foreach ($ids as $id)
            {
                $image = AlbumImage::where('id', $id)
                    ->select('id', 'url', 'width', 'height', 'size', 'type')
                    ->first();

                if (is_null($image))
                {
                    continue;
                }
                $images[] = $image->toArray();
            }

            if (empty($images))
            {
                return [];
            }

            $imageTransformer = new ImageTransformer();

            return $imageTransformer->album($images);
        });
    }

    public function getCartoonParts($bangumiId)
    {
        return $this->Cache($this->cacheKeyCartoonParts($bangumiId), function () use ($bangumiId)
        {
            return Image::where('bangumi_id', $bangumiId)
                ->where('is_cartoon', true)
                ->orderBy('part', 'ASC')
                ->select('id', 'name', 'part')
                ->get()
                ->toArray();
        });
    }

    public function getBangumiCartoonIds($bangumiId, $page, $take, $sort = 'desc')
    {
        $parts = $this->getCartoonParts($bangumiId);
        if (empty($parts))
        {
            return null;
        }

        $ids = array_map(function ($item)
        {
            return $item['id'];
        }, $parts);

        if ($sort === 'desc')
        {
            $ids = array_reverse($ids);
        }

        return $this->filterIdsByPage($ids, $page, $take);
    }

    public function getRoleImageIds($roleId, $seen, $take, $size, $tags, $creator, $sort)
    {
        return Image::whereIn('state', [1, 4])
            ->whereRaw('role_id = ? and image_count <> ?', [$roleId, 1])
            ->whereNotIn('images.id', $seen)
            ->take($take)
            ->when($sort === 'new', function ($query)
            {
                return $query->latest();
            }, function ($query)
            {
                return $query->orderBy('like_count', 'DESC');
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
    }

    public function checkHasPartCartoon($bangumiId, $part)
    {
        return (int)Image::where('is_cartoon', 1)
            ->where('bangumi_id', $bangumiId)
            ->where('part', $part)
            ->whereNotNull('image_ids')
            ->count();
    }

    public function imageCreateSuccessProcess($imageId, $userId, $bangumiId)
    {
        $this->ListInsertBefore($this->cacheKeyUserImageIds($userId), $imageId);
        $imageTrendingService = new ImageTrendingService(0, $bangumiId);
        $imageTrendingService->create($imageId);
        // TODO：SEO
        // TODO：search
    }

    public function getUserImageIds($userId, $page, $take)
    {
        $ids = $this->RedisList($this->cacheKeyUserImageIds($userId), function () use ($userId)
        {
            return Image::where('user_id', $userId)
                ->orderBy('created_at', 'DESC')
                ->pluck('id');
        });

        return $this->filterIdsByPage($ids, $page, $take);
    }

    public function cacheKeyImageItem($id)
    {
        return 'image_' . $id;
    }

    public function cacheKeyAlbumImages($albumId)
    {
        return 'album_' . $albumId . '_images';
    }

    public function cacheKeyCartoonParts($bangumiId)
    {
        return 'bangumi_' . $bangumiId . '_cartoon_parts';
    }

    public function cacheKeyUserImageIds($userId)
    {
        return 'user_' . $userId . '_image_ids';
    }
}