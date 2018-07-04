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
    /**
     * 根据关键字搜索番剧
     *
     * @Get("/search/index")
     *
     * @Parameters({
     *      @Parameter("q", description="关键字", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧的 url"})
     * })
     */
    // TODO：支持更多类型的搜索
    // TODO：番剧不要只返回 url，还要返回其它信息
    public function index(Request $request)
    {
        $key = $request->get('q');
        if (!$key)
        {
            return $this->resOK();
        }

        $search = new Search();
        $result = $search->index($request->get('q'));

        return $this->resOK(empty($result) ? '' : '/bangumi/' . $result[0]['fields']['type_id']);
    }

    public function search(Request $request)
    {
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = intval($request->get('type')) ?: 0;
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
