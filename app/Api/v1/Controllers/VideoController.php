<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\VideoTransformer;
use App\Models\Video;
use App\Api\V1\Repositories\BangumiRepository;
use Illuminate\Support\Facades\Cache;

/**
 * @Resource("视频相关接口")
 */
class VideoController extends Controller
{
    /**
     * 获取视频资源
     *
     * @Get("/video/${videoId}/show")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"info": "视频对象", "bangumi": "番剧信息", "list": {"total": "视频总数", "repeat": "是否重排", "videos": "视频列表"}}}),
     *      @Response(404, body={"code": 404, "data": "不存在的视频资源"})
     * })
     */
    public function show($id)
    {
        $videoRepository = new VideoRepository();
        $info = $videoRepository->item($id);

        if (is_null($info))
        {
            return $this->res('不存在的视频资源', 404);
        }

        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($info['bangumi_id']);
        $season = json_decode($bangumi['season']);
        $list = $bangumiRepository->videos($bangumi['id'], $season);

        $bangumiTransformer = new BangumiTransformer();
        $videoTransformer = new VideoTransformer();

        return $this->res([
            'info' => $videoTransformer->show($info),
            'bangumi' => $bangumiTransformer->item($bangumi),
            'season' => $season,
            'list' => $list
        ]);
    }

    /**
     * 记录视频播放信息
     *
     * @Get("/video/${videoId}/playing")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"}),
     */
    public function playing($id)
    {
        $key = 'video_played_counter_' . $id;
        $value = Cache::rememberForever($key, function () use ($id)
        {
            return [
                'data' => Video::where('id', $id)->pluck('count_played')->first(),
                'time' => time()
            ];
        });

        $value['data']++;
        if (time() - $value['time'] > config('cache.ttl'))
        {
            $value['time'] = time();
            Video::find($id)->update([
                'count_played' => $value['data']
            ]);
        }

        Cache::forever($key, $value);

        return $this->res();
    }
}
