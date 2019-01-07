<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Models\Bangumi;
use App\Models\Video;
use Carbon\Carbon;
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
     * 搜索接口
     *
     * > 目前支持的参数格式：
     * type：all, user, bangumi, video，post，role，image，score，question，answer
     * 返回的数据与 flow/list 返回的相同
     *
     * @Get("/search/new")
     *
     * @Parameters({
     *      @Parameter("type", description="要检测的类型", type="string", required=true),
     *      @Parameter("q", description="搜索的关键词", type="string", required=true),
     *      @Parameter("page", description="搜索的页码", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body="数据列表")
     * })
     */
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
        $result = $search->retrieve(strtolower($key), $type, $page);

        return $this->resOK($result);
    }

    /**
     * 获取所有番剧列表
     *
     * > 返回所有的番剧列表，用户搜索提示，可以有效减少请求数
     *
     * @Get("/search/bangumis")
     *
     * @Transaction({
     *      @Response(200, body="番剧列表")
     * })
     */
    public function bangumis()
    {
        $bangumiRepository = new BangumiRepository();

        return $this->resOK($bangumiRepository->searchAll());
    }

    public function migrate()
    {
        $bangumiIds = Bangumi
            ::where('migration_state', 0)
            ->pluck('id')
            ->take(100)
            ->toArray();

        if (empty($bangumiIds))
        {
            return 'over';
        }
        $bangumiRepository = new BangumiRepository();
        foreach ($bangumiIds as $bid)
        {
            $bangumi = $bangumiRepository->item($bid);
            $videoObj = $bangumiRepository->videos($bid, json_decode($bangumi['season']));
            $hasSeason = $videoObj['has_season'];
            if ($hasSeason)
            {
                foreach ($videoObj['videos'] as $rank => $videoPackage)
                {
                    $videos = $videoPackage['data'];
                    $videoIds = [];
                    foreach ($videos as $video)
                    {
                        $videoIds[] = $video['id'];
                    }
                    $isLast = $rank === count($videoPackage) - 1;
                    $seasonId = DB
                        ::table('bangumi_seasons')
                        ->insertGetId([
                            'bangumi_id' => $bid,
                            'name' => $videoPackage['name'],
                            'rank' => $rank + 1,
                            'summary' => $bangumi['summary'],
                            'avatar' => $bangumi['avatar'],
                            'other_site_video' => intval($bangumi['others_site_video']),
                            'released_at' => $isLast ? $bangumi['released_at'] : 0,
                            'released_time' => $isLast ? $bangumi['released_time'] : 0,
                            'end' => $isLast ? intval($bangumi['end']) : 1,
                            'published_at' => Carbon::createFromTimestamp(time($videoPackage['time']))->toDateTimeString(),
                            'created_at' => $bangumi['created_at'],
                            'updated_at' => $bangumi['created_at'],
                            'videos' => empty($videoIds) ? '' : implode(',', $videoIds)
                        ]);

                    foreach ($videos as $video)
                    {
                        Video
                            ::where('id', $video['id'])
                            ->update([
                                'bangumi_season_id' => $seasonId,
                                'episode' => $video['part'] - $videoPackage['base']
                            ]);
                    }
                }
            }
            else
            {
                $videos = $videoObj['videos'];
                if (gettype($videos) === 'array')
                {
                    $videos = $videos[0];
                }
                $videoIds = [];
                foreach ($videos['data'] as $video)
                {
                    $videoIds[] = $video['id'];
                }

                $seasonId = DB
                    ::table('bangumi_seasons')
                    ->insertGetId([
                        'bangumi_id' => $bid,
                        'name' => '',
                        'rank' => 1,
                        'summary' => $bangumi['summary'],
                        'avatar' => $bangumi['avatar'],
                        'other_site_video' => intval($bangumi['others_site_video']),
                        'released_at' => $bangumi['released_at'],
                        'released_time' => $bangumi['released_time'],
                        'end' => intval($bangumi['end']),
                        'published_at' => Carbon::createFromTimestamp($bangumi['published_at'])->toDateTimeString(),
                        'created_at' => $bangumi['created_at'],
                        'updated_at' => $bangumi['created_at'],
                        'videos' => empty($videoIds) ? '' : implode(',', $videoIds)
                    ]);

                foreach ($videos['data'] as $video)
                {
                    Video
                        ::where('id', $video['id'])
                        ->update([
                            'bangumi_season_id' => $seasonId,
                            'episode' => $video['part'] - $videos['base']
                        ]);
                }
            }
            Bangumi
                ::where('id', $bid)
                ->update([
                    'has_season' => $hasSeason,
                    'migration_state' => 1
                ]);
        }

        return 'success';
    }
}
