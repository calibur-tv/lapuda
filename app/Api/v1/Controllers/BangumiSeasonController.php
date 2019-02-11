<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\v1\Repositories\BangumiSeasonRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\VirtualCoinService;
use App\Api\V1\Transformers\BangumiTransformer;
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

        $bangumiSeasonRepository = new BangumiSeasonRepository();
        $result = $bangumiRepository->videos($id);
        $season = $bangumiSeasonRepository->listByBangumiId($id);
        $resultSeason = [];
        foreach ($season as $item)
        {
            $resultSeason[] = [
                'id' => $item['id'],
                'name' => $item['name']
            ];
        }
        $result['season'] = $resultSeason;

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
        $otherSiteVideo = $request->get('other_site_video');

        $arr = [
            'name' => $request->get('name'),
            'rank' => $request->get('rank'),
            'summary' => $request->get('summary'),
            'avatar' => $request->get('avatar'),
            'published_at' => Carbon::createFromTimestamp($request->get('published_at'))->toDateTimeString(),
            'released_at' => $request->get('released_at'),
            'copyright_type' => $request->get('copyright_type'),
            'other_site_video' => $otherSiteVideo,
            'end' => $request->get('end'),
            'updated_at' => Carbon::now(),
        ];

        $result = BangumiSeason
            ::where('id', $bangumiSeasonId)
            ->update($arr);

        $bangumiSeasonRepository = new BangumiSeasonRepository();
        $bangumiSeasonRepository->updateVideoBySeasonId($bangumiSeasonId, $otherSiteVideo);

        if ($result === false)
        {
            return $this->resErrBad('更新失败');
        }

        Redis::DEL('bangumi_season:bangumi:'.$bangumiId);
        Redis::DEL("bangumi_{$bangumiId}_videos");

        return $this->resNoContent();
    }

    public function all(Request $request)
    {
        $query_other_site = $request->get('other_site');
        $query_released_at = $request->get('released_at');
        $query_copyright_type = $request->get('copyright_type');

        $result = BangumiSeason
            ::select('id', 'name', 'rank', 'bangumi_id', 'other_site_video', 'copyright_type','copyright_provider')
            ->when($query_other_site !== '', function ($query) use ($query_other_site)
            {
                return $query->where('other_site_video', $query_other_site == 1);
            })
            ->when($query_copyright_type !== '', function ($query) use ($query_copyright_type)
            {
                return $query->where('copyright_type', $query_copyright_type);
            })
            ->when($query_released_at !== '', function ($query) use ($query_released_at)
            {
                return $query->where('released_at', $query_released_at);
            })
            ->get()
            ->toArray();

        $bangumiRepository = new BangumiRepository();
        foreach ($result as $i => $season)
        {
            $season['bangumi'] = $bangumiRepository->item($season['bangumi_id']);
            $result[$i] = $season;
        }

        return $this->resOK($result);
    }

    public function update_season_key(Request $request)
    {
        $id = $request->get('season_id');
        $bangumiId = $request->get('bangumi_id');
        $key = $request->get('key');
        $val = $request->get('val');

        $bangumiSeasonRepository = new BangumiSeasonRepository();

        if ($key === 'other_site_video')
        {
            $bangumiSeasonRepository->updateVideoBySeasonId($id, $val);
        }
        else
        {
            BangumiSeason
                ::where('id', $id)
                ->update([
                    $key => $val
                ]);
        }
        Redis::DEL('bangumi_season:bangumi:'.$bangumiId);
        Redis::DEL("bangumi_{$bangumiId}_videos");

        return $this->resNoContent();
    }

    public function videoControl(Request $request)
    {
        $is_down = $request->get('is_down') === '1';

        $bangumiSeasonRepository = new BangumiSeasonRepository();

        $ids = BangumiSeason
            ::where('copyright_provider', 1) // bilibli
            ->where('copyright_type', 2) // 独家播放
            ->pluck('id')
            ->toArray();

        foreach ($ids as $id)
        {
            $bangumiSeasonRepository->updateVideoBySeasonId($id, $is_down);
        }

        $ids = BangumiSeason
            ::where('copyright_provider', '<>', 1) // 不是 bilibili
            ->whereIn('copyright_type', [2, 3, 4]) // 独家播放和收费
            ->pluck('id')
            ->toArray();

        foreach ($ids as $id)
        {
            $bangumiSeasonRepository->updateVideoBySeasonId($id, $is_down);
        }

        return $this->resOK();
    }
}
