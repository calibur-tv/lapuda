<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/12
 * Time: 下午10:32
 */

namespace App\Api\V1\Repositories;


use App\Models\BangumiSeason;
use App\Models\Video;

class VideoRepository extends Repository
{
    public function item($id, $isShow = false, $isPC = false)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->Cache('video_'.$id, function () use ($id)
        {
            $video = Video
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($video))
            {
                return null;
            }

            $video = $video->toArray();
            $src_1080 = "";
            $src_720 = "";
            $src_480 = "";
            $src_other = $video['url'];
            $other_site = BangumiSeason
                ::where('id', $video['bangumi_season_id'])
                ->pluck('other_site_video')
                ->first();

            if (!$other_site)
            {
                $resource = $video['resource'] === 'null' ? null : json_decode($video['resource'], true);

                if (isset($resource['video'][720]) && isset($resource['video'][720]['src']) && $resource['video'][720]['src'])
                {
                    $src_720 = $this->computeVideoSrc($resource['video'][720]['src']);
                }
                if (isset($resource['video'][1080]) && isset($resource['video'][1080]['src']) && $resource['video'][1080]['src'])
                {
                    $src_1080 = $this->computeVideoSrc($resource['video'][1080]['src']);
                }
                if (isset($resource['video'][480]) && isset($resource['video'][480]['src']) && $resource['video'][480]['src'])
                {
                    $src_480 = $this->computeVideoSrc($resource['video'][480]['src']);
                }
            }

            return [
                'id' => $video['id'],
                'src_480' => $src_480,
                'src_720' => $src_720,
                'src_1080' => $src_1080,
                'src_other' => $src_other,
                'poster' => $video['poster'],
                'other_site' => $other_site,
                'part' => $video['episode'],
                'name' => $video['name'],
                'is_creator' => $video['is_creator'],
                'bangumi_id' => $video['bangumi_id'],
                'episode' => $video['episode'],
                'user_id' => $video['user_id'],
                'deleted_at' => $video['deleted_at']
            ];
        }, 'h');

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
        }

        $otherSiteResource = $result['src_other'] ?: '';

        if ($result['other_site'])
        {
            $result['src'] = $otherSiteResource;
        }
        else if (!$isPC && $result['src_480'])
        {
            $result['src'] = $result['src_480'];
        }
        else
        {
            $result['src'] = $result['src_720'] ?: $result['src_1080'];
        }
        $result['src'] = $result['src'] ?: $otherSiteResource;
        if (!preg_match('/calibur.tv/', $result['src']))
        {
            $result['other_site'] = true;
        }

        return $result;
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $video = $this->item($id);
        $content = $video['name'];

        $job = (new \App\Jobs\Search\Index($type, 'video', $id, $content));
        dispatch($job);
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