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
use Illuminate\Support\Facades\Redis;


class CallbackController extends Controller
{
    public function qiniuAvthumb(Request $request)
    {
        $videoId = $request->get('id');
        $video = Video::where('id', $videoId)->first();
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

            if (isset($resource['video']['0']))
            {
                $originlSrc = $resource['video']['0']['src'];
                $other_site = 0;
            }
            else if (isset($resource['video']['720']))
            {
                $originlSrc = $resource['video']['720']['src'];
                $other_site = 0;
            }
            else if (isset($resource['video']['1080']))
            {
                $originlSrc = $resource['video']['1080']['src'];
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

            $resource['video']['0']['src'] = $originlSrc;
            foreach ($ret['items'] as $item)
            {
                if ($item['code'] != 0)
                {
                    Video::where('id', $videoId)
                        ->update([
                            'process' => '-' . $notifyId
                        ]);

                    return response()->json(['data' => 'something error'], 403);
                }

                $rate = explode('-', $item['key']);
                $rate = end($rate);
                $rate = explode('.', $rate)[0];
                $resource['video'][$rate]['src'] = $item['key'];
            }

            Video
                ::where('id', $videoId)
                ->update([
                    'process' => '1',
                    'resource' => json_encode($resource)
                ]);
        }

        Redis::DEL('video_' . $videoId);

        return response('', 200);
    }

    public function qiniuUploadImage(Request $request)
    {
        return response()->json($request->all(), 200);
    }
}