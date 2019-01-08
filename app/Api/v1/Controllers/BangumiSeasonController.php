<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\v1\Repositories\BangumiSeasonRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Models\Bangumi;
use App\Models\BangumiSeason;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BangumiSeasonController extends Controller
{
    /**
     * 新番列表（周）
     *
     * @Get("/bangumi/released")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧列表"})
     * })
     */
    public function released()
    {
        $data = Cache::remember('bangumi_release_list', 60, function ()
        {
            $list = BangumiSeason
                ::where('released_at', '<>', 0)
                ->orderBy('released_time', 'DESC')
                ->get()
                ->toArray();

            $result = [
                [], [], [], [], [], [], [], []
            ];

            $bangumiRepository = new BangumiRepository();

            foreach ($list as $item)
            {
                $bangumi = $bangumiRepository->item($item['bangumi_id']);

                $item['id'] = $bangumi['id'];
                $item['name'] = $bangumi['name'];
                $item['update'] = time() - $item['released_time'] < 604800;

                $videos = $item['videos'] ? explode(',', $item['videos']) : [];

                if (empty($videos))
                {
                    $item['released_video_id'] = 0;
                    $item['released_part'] = '0';
                }
                else
                {
                    $videoRepository = new VideoRepository();
                    $video = $videoRepository->item(last($videos));
                    $item['released_video_id'] = $video['id'];
                    $item['released_part'] = $video['episode'];
                }

                $result[$item['released_at']][] = $item;
                $result[0][] = $item;
            }

            $bangumiTransformer = new BangumiTransformer();
            foreach ($result as $i => $arr)
            {
                $result[$i] = $bangumiTransformer->released($arr);
            }

            return $result;
        });

        return $this->resOK($data);
    }

    /**
     * 番剧视频
     *
     * @Get("/bangumi/`bangumiId`/videos")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"videos": "视频列表", "has_season": "是否有多个季度", "total": "视频总数"}}),
     *      @Response(404, body={"code": 40401, "message": "不存在的番剧"})
     * })
     */
    public function bangumiVideos($id)
    {
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($id);

        if (is_null($bangumi))
        {
            return $this->resErrNotFound('没有找到番剧');
        }

        $result = $bangumiRepository->videos($id);

        return $this->resOK($result);
    }

    /**
     * 获取番剧的季度信息
     *
     * @Get("/bangumi/seasons")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "季度列表"})
     * })
     */
    public function list(Request $request)
    {
        $bangumiId = $request->get('bangumi_id');

        $bangumiSeasonRepository = new BangumiSeasonRepository();
        $seasons = $bangumiSeasonRepository->listByBangumiId($bangumiId);

        return $this->resOK($seasons);
    }

    // TODO，根据时间维度和标签来筛选番剧
    public function category()
    {

    }

    /**
     * 创建一个季度
     */
    public function create(Request $request)
    {
        $time = Carbon::now();
        $bangumiId = $request->get('bangumi_id');
        BangumiSeason::insertGetId([
            'bangumi_id' => $request->get('bangumi_id'),
            'name' => $request->get('name'),
            'rank' => $request->get('rank'),
            'summary' => $request->get('summary'),
            'avatar' => $request->get('avatar'),
            'published_at' => Carbon::createFromTimestamp($request->get('published_at'))->toDateTimeString(),
            'other_site_video' => $request->get('other_site_video'),
            'released_at' => $request->get('released_at'),
            'videos' => '',
            'end' => $request->get('end'),
            'created_at' => $time,
            'updated_at' => $time
        ]);

        Redis::DEL('bangumi_season:bangumi:'.$bangumiId);

        return $this->resCreated($bangumiId);
    }

    /**
     * 编辑番剧的季度信息
     */
    public function edit(Request $request)
    {
        $bangumiSeasonId = $request->get('id');
        $bangumiId = $request->get('bangumi_id');

        $arr = [
            'name' => $request->get('name'),
            'rank' => $request->get('rank'),
            'summary' => $request->get('summary'),
            'avatar' => $request->get('avatar'),
            'published_at' => Carbon::createFromTimestamp($request->get('published_at'))->toDateTimeString(),
            'released_at' => $request->get('released_at'),
            'other_site_video' => $request->get('other_site_video'),
            'end' => $request->get('end'),
            'updated_at' => Carbon::now(),
        ];

        $result = BangumiSeason
            ::where('id', $bangumiSeasonId)
            ->update($arr);

        if ($result === false)
        {
            return $this->resErrBad('更新失败');
        }

        Redis::DEL('bangumi_season:bangumi:'.$bangumiId);
        Redis::DEL("bangumi_{$bangumiId}_videos");

        return $this->resNoContent();
    }
}
