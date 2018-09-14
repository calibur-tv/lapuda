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
        $notifyId = $request->get('persistentId');
        $video = Video::where('process', $notifyId)->first();

        if (is_null($video))
        {
            return;
        }

        $auth = new \App\Services\Qiniu\Auth();
        $config = new Config();
        $pfop = new PersistentFop($auth, $config);

        list($ret, $err) = $pfop->status($notifyId);

        if ($err != null)
        {
            Video::where('process', $notifyId)
                ->update([
                    'process' => '-' . $notifyId
                ]);
        }
        else
        {
            $resource = $video['resource'] === 'null' ? null : json_decode($video['resource'], true);
            if (!$resource)
            {
                return;
            }

            if (isset($resource['video'][720]) && isset($resource['video'][720]['src']) && $resource['video'][720]['src'])
            {
                $resource['video'][1080]['src'] = $ret['items'][0]['key'];
                $other_site = 0;
            }
            else if (isset($resource['video'][1080]) && isset($resource['video'][1080]['src']) && $resource['video'][1080]['src'])
            {
                $resource['video'][1080]['src'] = $ret['items'][0]['key'];
                $other_site = 0;
            }
            else
            {
                $other_site = 1;
            }

            if ($other_site)
            {
                return;
            }

//            Video::where('process', $notifyId)
//                ->update([
//                    'process' => '1',
//                    'resource' => json_encode($resource)
//                ]);
        }

        return;
    }
}