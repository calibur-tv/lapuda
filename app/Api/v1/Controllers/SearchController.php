<?php

namespace App\Api\V1\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use App\Services\OpenSearch\Search;
use Illuminate\Support\Facades\DB;
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
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = $request->get('type') ?: 0;
        $page = $request->get('page') ?: 0;

        $search = new Search();
        $result = $search->index($key, $type, $page);

        return $this->resOK($result);
    }
}
