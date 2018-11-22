<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/14
 * Time: 下午9:21
 */

namespace App\Http\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Models\User;
use App\Models\UserZone;
use App\Models\Video;
use App\Services\Qiniu\Config;
use App\Services\Qiniu\Processing\PersistentFop;
use App\Services\Trial\UserIpAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Socialite;
use Overtrue\LaravelPinyin\Facades\Pinyin as Overtrue;
use Tymon\JWTAuth\Facades\JWTAuth;

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

    // QQ第三方登录
    public function qqAuthEntry(Request $request)
    {
        return Socialite
            ::driver('qq')
            ->redirect('https://api.calibur.tv/callback/auth/qq?' . http_build_query($request->all()));
    }

    // 微信开放平台登录
    public function wechatAuthEntry(Request $request)
    {
        $device = $request->get('device') === 'h5' ? 'h5' : 'pc';
        $scope = $device === 'h5' ? 'snsapi_userinfo' : 'snsapi_login';

        return Socialite
            ::driver('wechat')
            ->scopes([$scope])
            ->redirect('https://api.calibur.tv/callback/auth/wechat?' . http_build_query($request->all()));
    }

    public function qqAuthRedirect(Request $request)
    {
        $from = $request->get('from') === 'bind' ? 'bind' : 'sign';
        $code = $request->get('code');
        $state = $request->get('state');
        if (!$code || !$state)
        {
            return redirect('https://www.calibur.tv/callback/auth-error?message=' . '请求参数错误');
        }

        $user = Socialite
            ::driver('qq')
            ->user();

        $openId = $user['id'];
        $isNewUser = $this->isNewUser('qq_open_id', $openId);

        if ($from === 'bind')
        {
            if (!$isNewUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '该QQ号已绑定其它账号');
            }

            $userId = $request->get('id');
            $userZone = $request->get('zone');
            $hasUser = User
                ::where('id', $userId)
                ->where('zone', $userZone)
                ->count();

            if (!$hasUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '继续操作前请先登录');
            }

            User
                ::where('id', $userId)
                ->update([
                   'qq_open_id' => $openId
                ]);

            Redis::DEL('user_' . $userId);

            return redirect('https://www.calibur.tv/callback/auth-success?message=' . '已成功绑定QQ号');
        }

        if ($isNewUser)
        {
            // signUp
            $nickname = $this->getNickname($user['nickname']);
            $zone = $this->createUserZone($nickname);
            $data = [
                'nickname' => $nickname,
                'zone' => $zone,
                'qq_open_id' => $openId,
                'password' => bcrypt('calibur')
            ];

            try
            {
                $user = User::create($data);
            }
            catch (\Exception $e)
            {
                app('sentry')->captureException($e);

                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '请修改QQ昵称后重试');
            }

            $userRepository = new UserRepository();
            $userRepository->migrateSearchIndex('C', $user->id);
        }
        else
        {
            // signIn
            $user = User
                ::where('qq_open_id', $openId)
                ->first();
        }

        $userId = $user->id;
        $UserIpAddress = new UserIpAddress();
        $UserIpAddress->add(
            explode(', ', $request->headers->get('X-Forwarded-For'))[0],
            $userId
        );

        return redirect('https://www.calibur.tv/callback/auth-redirect?message=登录成功&token=' . $this->responseUser($user));
    }

    public function wechatAuthRedirect(Request $request)
    {
        $from = $request->get('from') === 'bind' ? 'bind' : 'sign';
        $code = $request->get('code');
        $state = $request->get('state');
        if (!$code || !$state)
        {
            return redirect('https://www.calibur.tv/callback/auth-error?message=' . '请求参数错误');
        }

        $user = Socialite
            ::driver('wechat')
            ->user();

        $openId = $user['original']['openid'];
        $uniqueId = $user['original']['unionid'];
        $isNewUser = $this->isNewUser('wechat_unique_id', $uniqueId);

        if ($from === 'bind')
        {
            if (!$isNewUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '该微信号已绑定其它账号');
            }

            $userId = $request->get('id');
            $userZone = $request->get('zone');
            $hasUser = User
                ::where('id', $userId)
                ->where('zone', $userZone)
                ->count();

            if (!$hasUser)
            {
                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '继续操作前请先登录');
            }

            User
                ::where('id', $userId)
                ->update([
                    'wechat_open_id' => $openId,
                    'wechat_unique_id' => $uniqueId
                ]);

            Redis::DEL('user_' . $userId);

            return redirect('https://www.calibur.tv/callback/auth-success?message=' . '已成功绑定微信号');
        }

        if ($isNewUser)
        {
            // signUp
            $nickname = $this->getNickname($user['nickname']);
            $zone = $this->createUserZone($nickname);
            $data = [
                'nickname' => $nickname,
                'zone' => $zone,
                'wechat_open_id' => $openId,
                'wechat_unique_id' => $uniqueId,
                'password' => bcrypt('calibur')
            ];

            try
            {
                $user = User::create($data);
            }
            catch (\Exception $e)
            {
                app('sentry')->captureException($e);

                return redirect('https://www.calibur.tv/callback/auth-error?message=' . '请修改微信昵称后重试');
            }

            $userRepository = new UserRepository();
            $userRepository->migrateSearchIndex('C', $user->id);
        }
        else
        {
            // signIn
            $user = User
                ::where('wechat_unique_id', $uniqueId)
                ->first();
        }

        $userId = $user->id;
        $UserIpAddress = new UserIpAddress();
        $UserIpAddress->add(
            explode(', ', $request->headers->get('X-Forwarded-For'))[0],
            $userId
        );

        return redirect('https://www.calibur.tv/callback/auth-redirect?message=登录成功&token=' . $this->responseUser($user));
    }

    protected function responseUser($user)
    {
        return JWTAuth::fromUser($user, [
            'remember' => $user->remember_token
        ]);
    }

    protected function createUserZone($name)
    {
        $pinyin = strtolower(Overtrue::permalink($name));

        $tail = UserZone::where('name', $pinyin)->pluck('count')->first();

        // 如果用户的昵称是中文加数字，生成的拼音会有可能被占用，从而注册的时候就失败了
        // 可以通过一个递归调用 createUserZone 来解决，但是太危险了，先不修复这个问题

        if ($tail)
        {
            UserZone::where('name', $pinyin)->increment('count');
            return $pinyin . '-' . implode('-', str_split(($tail), 2));
        }
        else
        {
            UserZone::create(['name' => $pinyin]);

            return $pinyin;
        }
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