<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/14
 * Time: 下午9:21
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use App\Services\Qiniu\Config;
use App\Services\Qiniu\Processing\PersistentFop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Socialite;

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
        $params = $request->all();
        $today = date("Y-m-d", time());
        return response()->json([
            'code' => 0,
            'data' => [
                'height' => (int)$params['height'],
                'width' => (int)$params['width'],
                'size' => (int)$params['size'],
                'type' => $params['type'],
                'url' => "user/{$params['uid']}/{$today}/{$params['key']}"
            ]
        ], 200);
    }

    public function qqAuthEntry(Request $request)
    {
        $from = $request->get('from')  === 'sign' ? 'sign' : 'bind';

        return Socialite
            ::driver('qq')
            ->redirect('https://api.calibur.tv/callback/auth/qq?from=' . $from);
    }

    public function wechatAuthEntry(Request $request)
    {
        $from = $request->get('from')  === 'sign' ? 'sign' : 'bind';

        return Socialite
            ::driver('wechat')
            ->redirect('https://api.calibur.tv/callback/auth/wechat?from=' . $from);
    }

    public function qqAuthRedirect()
    {
        $user = Socialite
            ::driver('qq')
            ->user();
        $openId = $user['id'];

        if ($this->isNewUser('qq_open_id', $openId))
        {
            // signUp
            $nickname = $this->getNickname($user['nickname']);
        }
        else
        {
            // signIn
        }

        return response([
            'data' => $user
        ], 200);
    }

    public function wechatAuthRedirect()
    {
        $user = Socialite
            ::driver('wechat')
            ->user();

        $openId = $user['original']['openid'];

        if ($this->isNewUser('wechat_open_id', $openId))
        {
            // signUp
            $nickname = $this->getNickname($user['nickname']);
            $uniqueId = $user['original']['unionid'];
        }
        else
        {
            // signIn
        }

        return response([
            'data' => $user
        ], 200);
    }

    protected function isNewUser($key, $value)
    {
        return !User::where($key, $value)->count();
    }

    protected function getNickname($nickname)
    {
        preg_match_all('/([a-zA-Z]+|[0-9]+|[\x{4e00}-\x{9fa5}]+)*/u', $nickname, $matches);

        return implode('', $matches[0]) ?: 'zero';
    }
}