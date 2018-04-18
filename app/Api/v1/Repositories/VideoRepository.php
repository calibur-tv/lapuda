<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/12
 * Time: 下午10:32
 */

namespace App\Api\V1\Repositories;


use App\Models\Video;

class VideoRepository extends Repository
{
    public function item($id)
    {
        return $this->Cache('video_'.$id, function () use ($id)
        {
            $video = Video::find($id);

            if (is_null($video))
            {
                return null;
            }

            $video = $video->toArray();
            $resource = $video['resource'] === 'null' ? null : json_decode($video['resource'], true);

            if (isset($resource['video'][720]) && isset($resource['video'][720]['src']))
            {
                $resource['video'][720]['src'] = $this->computeVideoSrc($resource['video'][720]['src']);
            }

            if (isset($resource['video'][1080]) && isset($resource['video'][1080]['src']))
            {
                $resource['video'][1080]['src'] = $this->computeVideoSrc($resource['video'][1080]['src']);
            }

            $video['resource'] = $resource;

            return $video;
        }, 'h');
    }


    protected function computeVideoSrc($src)
    {
        $t = base_convert(time() + 21600, 10, 16);

        $str = '/' . $src;
        $pos = strrpos($str, '/') + 1;
        $encodePath = substr($str, 0, $pos) . urlencode(substr($str, $pos));

        $sign = strtolower(md5(config('website.qiniu_time_key') . $encodePath . $t));

        return config('website.video') . $src . '?sign=' . $sign . '&t=' . $t;
    }
}