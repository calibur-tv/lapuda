<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/14
 * Time: 下午9:21
 */

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\Qiniu\Config;
use App\Services\Qiniu\Processing\PersistentFop;
use Illuminate\Http\Request;


class CallbackController extends Controller
{
    public function qiniuAvthumb(Request $request)
    {
        $videoId = $request->get('id');
        $video = Video::where('process', $videoId)->first();
        if (is_null($video))
        {
            return response()->json(['data' => 'video not found'], 404);
        }

        $notifyId = $video['process'];
        if (!$notifyId)
        {
            return response()->json(['data' => 'notify not found'], 404);
        }

        $auth = new \App\Services\Qiniu\Auth();
        $config = new Config();
        $pfop = new PersistentFop($auth, $config);

        list($ret, $err) = $pfop->status($notifyId);

        if ($err != null)
        {
            Video::where('id', $videoId)
                ->update([
                    'process' => '-' . $notifyId
                ]);
        }
        else
        {
            $resource = $video['resource'] === 'null' ? null : json_decode($video['resource'], true);
            if (!$resource)
            {
                return response()->json(['data' => 'resource is empyt'], 403);
            }

            if (isset($resource['video'][720]) && isset($resource['video'][720]['src']) && $resource['video'][720]['src'])
            {
                $originlSrc = $resource['video'][720]['src'];
                $other_site = 0;
            }
            else if (isset($resource['video'][1080]) && isset($resource['video'][1080]['src']) && $resource['video'][1080]['src'])
            {
                $originlSrc = $resource['video'][1080]['src'];
                $other_site = 0;
            }
            else
            {
                $originlSrc = '';
                $other_site = 1;
            }

            if ($other_site)
            {
                return response()->json(['data' => 'use other site resource'], 403);
            }

            $resource['video'][0]['src'] = $originlSrc;
            foreach ($ret['items'] as $item)
            {
                $rate = explode('.', end(explode('-', $item['key'])))[0];
                $resource['video'][$rate]['src'] = $item['key'];
            }

            Video
                ::where('id', $videoId)
                ->update([
                    'process' => '1',
                    'resource' => json_encode($resource)
                ]);
        }

        return response('', 204);
    }
}