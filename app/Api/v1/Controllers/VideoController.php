<?php

namespace App\Api\V1\Controllers;

use App\Models\Video;
use App\Api\V1\Repositories\BangumiRepository;
use Illuminate\Support\Facades\Cache;

class VideoController extends Controller
{
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
            return $this->resErr(['视频不存在']);
        }

        $bangumiRepository = new BangumiRepository();
        $data['bangumi'] = $bangumiRepository->item($data['info']['bangumi_id']);

        return $this->resOK($data);
    }

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

        return $this->resOK();
    }
}
