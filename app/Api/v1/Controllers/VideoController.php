<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\Counter\VideoPlayCounter;
use App\Api\V1\Transformers\VideoTransformer;
use App\Api\V1\Repositories\BangumiRepository;

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
     *      @Response(404, body={"code": 40401, "message": "不存在的视频资源", "data": ""})
     * })
     */
    public function show($id)
    {
        $videoRepository = new VideoRepository();
        $info = $videoRepository->item($id);

        if (is_null($info))
        {
            return $this->resErrNotFound('不存在的视频资源');
        }

        $userId = $this->getAuthUserId();
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($info['bangumi_id']);

        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $season = json_decode($bangumi['season']);
        $list = $bangumiRepository->videos($bangumi['id'], $season);

        $videoTransformer = new VideoTransformer();

        return $this->resOK([
            'info' => $videoTransformer->show($info),
            'bangumi' => $bangumiRepository->panel($info['bangumi_id'], $userId),
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
        $videoPlayCounter = new VideoPlayCounter();
        $videoPlayCounter->add($id);

        return $this->resNoContent();
    }
}
