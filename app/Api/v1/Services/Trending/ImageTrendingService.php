<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/9
 * Time: 上午7:48
 */

namespace App\Api\V1\Services\Trending;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Counter\ImageViewCounter;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Models\Image;
use Carbon\Carbon;

class ImageTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($visitorId = 0, $bangumiId = 0)
    {
        parent::__construct('images', $bangumiId);

        $this->visitorId = $visitorId;
        $this->bangumiId = $bangumiId;
    }

    public function news($minId, $take)
    {
        $idsObject = $this->getNewsIds($minId, $take);
        $list = $this->getImagesByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function active($seenIds, $take)
    {
        $idsObject = $this->getActiveIds($seenIds, $take);
        $list = $this->getImagesByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function hot($seenIds, $take)
    {
        $idsObject = $this->getHotIds($seenIds, $take);
        $list = $this->getImagesByIds($idsObject['ids']);

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function computeNewsIds()
    {
        return Image::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query->where('bangumi_id', $this->bangumiId);
            })
            ->orderBy('created_at', 'desc')
            ->where('is_album', 0)
            ->orWhere('image_ids', '<>', null)
            ->latest()
            ->take(100)
            ->pluck('id');
    }

    public function computeActiveIds()
    {
        return Image::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query->where('bangumi_id', $this->bangumiId);
            })
            ->orderBy('updated_at', 'desc')
            ->where('is_album', 0)
            ->orWhere('image_ids', '<>', null)
            ->latest()
            ->take(100)
            ->pluck('updated_at', 'id');
    }

    public function computeHotIds()
    {
        $ids = Image::where('created_at', '>', Carbon::now()->addDays(-30))
            ->when($this->bangumiId, function ($query)
            {
                return $query->where('bangumi_id', $this->bangumiId);
            })
            ->where('is_album', 0)
            ->orWhere('image_ids', '<>', null)
            ->pluck('id');

        $imageRepository = new ImageRepository();
        $imageLikeService = new ImageLikeService();
        $imageViewCounter = new ImageViewCounter();
        $imageCommentService = new ImageCommentService();

        $list = $imageRepository->list($ids);

        $result = [];
        // https://segmentfault.com/a/1190000004253816
        foreach ($list as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $postId = $item['id'];
            $likeCount = $imageLikeService->total($postId);
            $viewCount = $imageViewCounter->get($postId);
            $commentCount = $imageCommentService->getCommentCount($postId);

            $result[$postId] = (
                    $likeCount +
                    ($viewCount && log($viewCount, 10) * 4) +
                    ($commentCount && log($commentCount, M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.3);
        }

        return $result;
    }

    protected function getImagesByIds($ids)
    {
        $imageRepository = new ImageRepository();
        $imageCommentService = new ImageCommentService();
        $imageLikeService = new ImageLikeService();
        $imageViewService = new ImageViewCounter();
        $userRepository = new UserRepository();
        $bangumiRepository = new BangumiRepository();

        $images = $imageRepository->list($ids);
        $result = [];

        foreach ($images as $item)
        {
            if (is_null($item))
            {
                continue;
            }

            $user = $userRepository->item($item['user_id']);
            if (is_null($user))
            {
                continue;
            }

            $bangumi = $bangumiRepository->item($item['bangumi_id']);
            if (is_null($bangumi))
            {
                continue;
            }

            $result[] = $item;
        }

        if (empty($result))
        {
            return [];
        }

        $images = $imageCommentService->batchGetCommentCount($images);
        $images = $imageLikeService->batchTotal($images, 'like_count');
        $images = $imageViewService->batchGet($images, 'view_count');

        return $images;
    }
}