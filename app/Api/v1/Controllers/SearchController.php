<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\UserRepository;
use Illuminate\Http\Request;
use App\Services\OpenSearch\Search;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("搜索相关接口")
 */
class SearchController extends Controller
{
    public function search(Request $request)
    {
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = $request->get('type') ?: 'all';
        $page = intval($request->get('page')) ?: 0;

        $search = new Search();
        $result = $search->retrieve($key, $type, $page);

        return $this->resOK($result);
    }

    public function bangumis()
    {
        $bangumiRepository = new BangumiRepository();

        return $this->resOK($bangumiRepository->searchAll());
    }

    public function getUserInfo(Request $request)
    {
        $zone = $request->get('zone');
        $userId = $request->get('id');
        if (!$zone && !$userId)
        {
            return $this->resErrBad();
        }

        $userRepository = new UserRepository();
        if (!$userId)
        {
            $userId = $userRepository->getUserIdByZone($zone, true);
        }

        if (!$userId)
        {
            return $this->resErrNotFound();
        }

        return $this->resOK($userRepository->item($userId, true));
    }
}
