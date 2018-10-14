<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/9
 * Time: 上午7:48
 */

namespace App\Api\V1\Services\Trending;

use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Counter\ImageViewCounter;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Toggle\Image\ImageMarkService;
use App\Api\V1\Services\Toggle\Image\ImageRewardService;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Transformers\ImageTransformer;
use App\Models\Image;
use Carbon\Carbon;

class ImageTrendingService extends TrendingService
{
    protected $bangumiId;
    protected $userId;

    public function __construct($bangumiId = 0, $userId = 0)
    {
        parent::__construct('images', $bangumiId, $userId);
    }

    public function computeNewsIds()
    {
        return Image
            ::where('state', 0)
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
        return Image
            ::where('state', 0)
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
            ->orderBy('updated_at', 'desc')
            ->take(300)
            ->pluck('updated_at', 'id');
    }

    public function computeHotIds()
    {
        $ids = Image
            ::where('state', 0)
            ->where('created_at', '>', Carbon::now()->addDays(-100))
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
        $imageMarkService = new ImageMarkService();
        $imageRewardService = new ImageRewardService();
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

            $imageId = $item['id'];
            $likeCount = $imageLikeService->total($imageId);
            $markCount = $imageMarkService->total($imageId);
            $rewardCount = $imageRewardService->total($imageId);
            $viewCount = $imageViewCounter->get($imageId);
            $commentCount = $imageCommentService->getCommentCount($imageId);

            $result[$imageId] = (
                    ($viewCount && log($viewCount, 10) * 4) +
                    ($likeCount * 2 + $markCount * 2 + $rewardCount * 3) +
                    ($commentCount && log($commentCount, M_E))
                ) / pow((((time() * 2 - strtotime($item['created_at']) - strtotime($item['updated_at'])) / 2) + 1), 0.3);
        }

        return $result;
    }

    public function computeUserIds()
    {
        return Image
            ::where('user_id', $this->userId)
            ->where('is_cartoon', 0)
            ->latest()
            ->pluck('id');
    }

    public function getListByIds($ids, $flowType)
    {
        $store = new ImageRepository();
        if ($flowType === 'bangumi')
        {
            $list = $store->bangumiFlow($ids);
        }
        else if ($flowType === 'user')
        {
            $list = $store->userFlow($ids);
        }
        else
        {
            $list = $store->trendingFlow($ids);
        }
        if (empty($list))
        {
            return [];
        }

        $likeService = new ImageLikeService();
        $rewardService = new ImageRewardService();
        $markService = new ImageMarkService();
        $commentService = new ImageCommentService();

        $list = $commentService->batchGetCommentCount($list);
        $list = $likeService->batchTotal($list, 'like_count');
        $list = $markService->batchTotal($list, 'mark_count');
        foreach ($list as $i => $item)
        {
            if ($item['is_creator'])
            {
                $list[$i]['like_count'] = 0;
                $list[$i]['reward_count'] = $rewardService->total($item['id']);
            }
            else
            {
                $list[$i]['like_count'] = $likeService->total($item['id']);
                $list[$i]['reward_count'] = 0;
            }
        }

        $transformer = new ImageTransformer();
        if ($flowType === 'bangumi')
        {
            return $transformer->bangumiFlow($list);
        }
        else if ($flowType === 'user')
        {
            return $transformer->userFlow($list);
        }

        return $transformer->trendingFlow($list);
    }
}