<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/23
 * Time: 上午10:08
 */

namespace App\Api\V1\Repositories;

use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Toggle\Image\ImageMarkService;
use App\Api\V1\Services\Toggle\Image\ImageRewardService;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Services\UserLevel;
use App\Api\V1\Transformers\ImageTransformer;
use App\Models\AlbumImage;
use App\Models\Banner;
use App\Models\Image;
use App\Services\BaiduSearch\BaiduPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ImageRepository extends Repository
{
    public function item($id, $isShow = false)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->Cache($this->itemCacheKey($id), function () use ($id)
        {
            $image = Image
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($image))
            {
                return null;
            }

            $image = $image->toArray();

            $image['image_count'] = count($this->albumImages($id));

            return $image;
        });

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
        }

        return $result;
    }

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
                'name' => $params['name'],
                'url' => $this->convertImagePath($params['url']),
                'width' => $params['width'],
                'height' => $params['height'],
                'size' => $params['size'],
                'type' => $params['type'],
                'part' => $params['part'],
                'state' => 0,
                'created_at' => $now,
                'updated_at' => $now
            ]);

        $job = (new \App\Jobs\Trial\Image\Create($newId));
        dispatch($job);

        return $newId;
    }

    public function uptoken($userId)
    {
        $now = time();
        $auth = new \App\Services\Qiniu\Auth();
        $timeout = 3600;
        $uptoken = $auth->uploadToken(null, $timeout, [
            'endUser' => "{$userId}",
            'returnBody' => '{
                "code": 0,
                "data": {
                    "height": $(imageInfo.height),
                    "width": $(imageInfo.width),
                    "type": "$(mimeType)",
                    "size": $(fsize),
                    "url": "$(key)"
                }
            }',
//            'callbackUrl' => 'https://api.calibur.tv/callback/qiniu/uploadimage',
//            'callbackBodyType' => 'application/json',
//            'callbackBody' => '{"key":"$(key)","uid": "$(endUser)","type":"$(mimeType)","size":"$(fsize)","width":"$(imageInfo.width)","height":"$(imageInfo.height)"}',
            'mimeLimit' => 'image/jpeg;image/png;image/jpg;image/gif'
        ]);

        return [
            'upToken' => $uptoken,
            'expiredAt' => $now + $timeout
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

            return $imageTransformer->albumImages($images);
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

    public function checkHasPartCartoon($bangumiId, $part)
    {
        return (int)Image::where('is_cartoon', 1)
            ->where('bangumi_id', $bangumiId)
            ->where('part', $part)
            ->whereNotNull('image_ids')
            ->count();
    }

    public function createProcess($id, $state = 0)
    {
        $image = $this->item($id, true);

        if ($state)
        {
            DB::table('images')
                ->where('id', $id)
                ->update([
                    'state' => $state
                ]);
        }

        $updateTrending = !$image['is_cartoon'];
        if (!$image['is_album'] || $image['image_count'] > 0)
        {
            $imageTrendingService = new ImageTrendingService($image['bangumi_id'], $image['user_id']);
            $imageTrendingService->create($id, $updateTrending);
        }

        $baiduPush = new BaiduPush();
        if ($updateTrending)
        {
            $baiduPush->trending('image');
            $baiduPush->bangumi($image['bangumi_id'], 'pins');
        }
        else
        {
            // 是漫画
            Redis::DEL($this->cacheKeyCartoonParts($image['bangumi_id']));
        }

        $this->migrateSearchIndex('C', $id, false);
    }

    public function updateProcess($id)
    {
        $image = $this->item($id);

        $this->migrateSearchIndex('U', $id, false);

        if ($image['is_cartoon'])
        {
            Redis::DEL($this->cacheKeyCartoonParts($image['bangumi_id']));
        }
    }

    public function deleteProcess($id, $state = 0)
    {
        $image = $this->item($id, true);
        $userId = $image['user_id'];

        DB::table('images')
            ->where('id', $id)
            ->update([
                'state' => $state,
                'deleted_at' => Carbon::now()
            ]);

        if ($state === 0 || $image['created_at'] !== $image['updated_at'])
        {
            $imageTrendingService = new ImageTrendingService($image['bangumi_id'], $userId);
            $imageTrendingService->delete($id);

            $job = (new \App\Jobs\Search\Index('D', 'image', $id));
            dispatch($job);
        }

        Redis::DEL($this->itemCacheKey($id));

        if ($image['is_album'])
        {
            AlbumImage::where('album_id', $id)->delete();
            Redis::DEL($this->cacheKeyAlbumImages($id));
            if ($image['is_cartoon'])
            {
                Redis::DEL($this->cacheKeyCartoonParts($image['bangumi_id']));
            }

            $totalImageCount = new TotalImageCount();
            $totalImageCount->add(-count(explode(',', $image['image_ids'])));
        }

        $imageRewardService = new ImageRewardService();
        $imageRewardService->cancel($id);

        $userLevel = new UserLevel();
        $exp = $userLevel->change($image['user_id'], -3, false);
        if ($image['is_creator'])
        {
            $total = $imageRewardService->total($id);
            $cancelEXP1 = $total * -3;
            $exp += $cancelEXP1;
        }
        else
        {
            $imageLikeService = new ImageLikeService();
            $total = $imageLikeService->total($id);
            $cancelEXP1 = $total * -2;
            $exp += $cancelEXP1;
        }
        $imageMarkService = new ImageMarkService();
        $total = $imageMarkService->total($id);
        $cancelEXP2 = $total * -2;
        $exp += $cancelEXP2;
        $userLevel->change($userId, $cancelEXP1 + $cancelEXP2, false);

        return $exp;
    }

    public function recoverProcess($id)
    {
        $image = $this->item($id, true);

        $imageTrendingService = new ImageTrendingService($image['bangumi_id'], $image['user_id']);
        $isDeleted = $image['deleted_at'];
        $isCartoon = $image['is_cartoon'];

        if ($isDeleted)
        {
            $imageTrendingService->create($id, !$isCartoon);

            $this->migrateSearchIndex('C', $id, false);
        }

        DB::table('images')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => null
            ]);

        if ($image['state'])
        {
            if (!$isDeleted && !$isCartoon)
            {
                $imageTrendingService->update($id, !$image['is_cartoon']);
            }
            Redis::DEL($this->itemCacheKey($id));
        }
    }

    public function itemCacheKey($id)
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

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $image = $this->item($id);
        $content = $image['name'];

        $job = (new \App\Jobs\Search\Index($type, 'image', $id, $content));
        dispatch($job);
    }
}