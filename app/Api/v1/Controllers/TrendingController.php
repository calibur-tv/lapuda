<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Counter\Stats\TotalBangumiCount;
use App\Api\V1\Services\Counter\Stats\TotalImageAlbumCount;
use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Api\V1\Services\Counter\Stats\TotalPostCount;
use App\Api\V1\Services\Counter\Stats\TotalScoreCount;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Api\V1\Services\Trending\RoleTrendingService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
use Illuminate\Http\Request;

/**
 * @Resource("排行相关接口")
 */
class TrendingController extends Controller
{
    public function news(Request $request)
    {
        $bangumiId = $request->get('bangumiId') ?: 0;
        $type = $request->get('type');
        $take = $request->get('take') ?: 10;
        $trendingService = $this->getTrendingServiceByType($type, $bangumiId);
        if (is_null($trendingService))
        {
            return $this->resErrBad();
        }

        $minId = intval($request->get('minId')) ?: 0;

        return $this->resOK($trendingService->news($minId, $take));
    }

    public function active(Request $request)
    {
        $bangumiId = $request->get('bangumiId') ?: 0;
        $type = $request->get('type');
        $take = $request->get('take') ?: 10;
        $trendingService = $this->getTrendingServiceByType($type, $bangumiId);
        if (is_null($trendingService))
        {
            return $this->resErrBad();
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];

        return $this->resOK($trendingService->active($seen, $take));
    }

    public function hot(Request $request)
    {
        $bangumiId = $request->get('bangumiId') ?: 0;
        $type = $request->get('type');
        $take = $request->get('take') ?: 10;
        $trendingService = $this->getTrendingServiceByType($type, $bangumiId);
        if (is_null($trendingService))
        {
            return $this->resErrBad();
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];

        return $this->resOK($trendingService->hot($seen, $take));
    }

    public function users(Request $request)
    {
        $zone = $request->get('zone');
        $type = $request->get('type');
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 10;
        $userRepository = new UserRepository();
        $userId = $userRepository->getUserIdByZone($zone);
        if (!$userId)
        {
            return $this->resErrNotFound();
        }

        $trendingService = $this->getTrendingServiceByType($type, 0, $userId);
        if (is_null($trendingService))
        {
            return $this->resErrBad();
        }

        return $this->resOK($trendingService->users($page, $take));
    }

    public function meta(Request $request)
    {
        $type = $request->get('type');
        if ($type === 'post')
        {
            $collectionCount = new TotalBangumiCount();
            $totalCount = new TotalPostCount();
        }
        else if ($type === 'image')
        {
            $collectionCount = new TotalImageAlbumCount();
            $totalCount = new TotalImageCount();
        }
        else if ($type === 'score')
        {
            $collectionCount = new TotalBangumiCount();
            $totalCount = new TotalScoreCount();
        }
        else
        {
            return $this->resErrBad();
        }

        return $this->resOK([
            'collection' => $collectionCount->get(),
            'total' => $totalCount->get()
        ]);
    }

    protected function getTrendingServiceByType($type, $bangumiId = 0, $userId = 0)
    {
        if ($type === 'post')
        {
            return new PostTrendingService($bangumiId, $userId);
        }
        else if ($type === 'image')
        {
            return new ImageTrendingService($bangumiId, $userId);
        }
        else if ($type === 'score')
        {
            return new ScoreTrendingService($bangumiId, $userId);
        }
        else if ($type === 'role')
        {
            return new RoleTrendingService($bangumiId, $userId);
        }

        return null;
    }
}
