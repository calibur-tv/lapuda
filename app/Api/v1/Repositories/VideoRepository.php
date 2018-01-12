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
                $resource['video'][720]['src'] = config('website.video') . $resource['video'][720]['src'];
            }

            if (isset($resource['video'][1080]) && isset($resource['video'][1080]['src']))
            {
                $resource['video'][1080]['src'] = config('website.video') . $resource['video'][1080]['src'];
            }

            $video['resource'] = $resource;

            return $video;
        });
    }
}