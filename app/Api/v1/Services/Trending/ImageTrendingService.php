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
use App\Api\V1\Transformers\ImageTransformer;
use App\Models\Image;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

    public function computeNewsIds()
    {
        return Image::where('state', 0)
            ->when($this->bangumiId, function ($query)
            {
                return $query->where('bangumi_id', $this->bangumiId);
            })
            ->where('is_album', 0)
            ->orWhere($this->bangumiId ? [
                ['image_ids', '<>', null],
                ['is_cartoon', 0],
                ['bangumi_id', $this->bangumiId]
            ] : [
                ['image_ids', '<>', null],
                ['is_cartoon', 0]
            ])
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
            ->where('is_album', 0)
            ->orWhere($this->bangumiId ? [
                ['image_ids', '<>', null],
                ['is_cartoon', 0],
                ['bangumi_id', $this->bangumiId]
            ] : [
                ['image_ids', '<>', null],
                ['is_cartoon', 0]
            ])
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
            ->orWhere($this->bangumiId ? [
                ['image_ids', '<>', null],
                ['is_cartoon', 0],
                ['bangumi_id', $this->bangumiId]
            ] : [
                ['image_ids', '<>', null],
                ['is_cartoon', 0]
            ])
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

    protected function getListByIds($ids)
    {
        $imageRepository = new ImageRepository();
        $imageCommentService = new ImageCommentService();
        $imageLikeService = new ImageLikeService();
        $imageViewService = new ImageViewCounter();

        $images = $imageRepository->trendingFlow($ids);

        $images = $imageCommentService->batchGetCommentCount($images);
        $images = $imageLikeService->batchTotal($images, 'like_count');
        $images = $imageViewService->batchGet($images, 'view_count');

        $imageTransformer = new ImageTransformer();

        return $imageTransformer->trendingFlow($images);
    }
}