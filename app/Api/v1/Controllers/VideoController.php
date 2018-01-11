<?php

namespace App\Api\V1\Controllers;

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
     *      @Response(200, body={"code": 0, "data": "..."}),
     *      @Response(404, body={"code": 404, "data": "不存在的视频资源"})
     * })
     */
    public function show($id)
    {
        $data = Cache::remember('video_'.$id.'_show', config('cache.ttl'), function () use ($id)
        {
            $info = Video::where('id', $id)->first();
            if (is_null($info))
            {
                return null;
            }

            $info['resource'] = $info['resource'] === 'null' ? '' : json_decode($info['resource']);

            return [
                'info' => $info,
                'videos' => Video::where('bangumi_id', $info['bangumi_id'])->get()
            ];
        });

        if (is_null($data))
        {
            return $this->res('视频不存在', 404);
        }

        $bangumiRepository = new BangumiRepository();
        $data['bangumi'] = $bangumiRepository->item($data['info']['bangumi_id']);

        return $this->res($data);
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
