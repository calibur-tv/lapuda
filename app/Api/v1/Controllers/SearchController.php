<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\VideoRepository;
use App\Models\Video;
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

    public function migrate(Request $request)
    {
        $type = intval($request->get('type')) ?: 0;
        $search = new Search();

        if ($type === 3)
        {
            $videos = Video::where('id', '>', 13512)->pluck('id');
            $videoRepository = new VideoRepository();
            foreach ($videos as $videoId)
            {
                $video = $videoRepository->item($videoId);
                $search->create(
                    $video['id'],
                    $video['name'],
                    'video',
                    strtotime($video['created_at'])
                );
            }
        }

        // TODO image search

        return 'success';
    }
}
