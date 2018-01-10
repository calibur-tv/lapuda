<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Requests\User\RegisterRequest;
use App\Api\V1\Transformers\UserTransformer;
use App\Mail\Welcome;
use App\Models\Confirm;
use App\Models\User;
use App\Models\UserZone;
use App\Api\V1\Repositories\ImageRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Overtrue\LaravelPinyin\Facades\Pinyin as Overtrue;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @Resource("用户认证相关接口")
 */
class DoorController extends Controller
{
    public function __construct()
    {
        $this->middleware('geetest')->only([
            'login', 'register'
        ]);
    }

    public function sendEmailOrMessage(Request $request)
    {
        $method = $request->get('method');
        $access = $request->get('access');
        $nickname = $request->get('nickname');
        $mustNotRegister = $request->get('mustNotRegister');
        $mustRegistered = $request->get('mustRegistered');

        if ( ! in_array($method, ['phone', 'email']))
        {
            return $this->resErr(['错误的发送渠道']);
        }

        if ( ! is_null($mustNotRegister) && $this->checkAccessCanUse($method, $access) !== 0)
        {
            return $this->resErr([$method === 'email' ? '邮箱已注册' : '手机号已注册']);
        }

        if ( ! is_null($mustRegistered) && $this->checkAccessCanUse($method, $access) !== 1)
        {
            return $this->resErr([$method === 'email' ? '未注册的邮箱' : '未注册的手机号']);
        }

        $token = $this->makeConfirm($access);

        if ($method === 'email') {
            Mail::send(new Welcome($access, $nickname, $token));
        } else {
            // TODO: send phone message
        }

        return $this->resOK();
    }

    public function register(RegisterRequest $request)
    {
        $method = $request->get('method');
        $access = $request->get('access');

        if ($this->checkAccessCanUse($method, $access)) {
            return $this->resErr(['该手机或邮箱已绑定另外一个账号'], 403);
        }

        if ($this->checkAuthCode($request->get('authCode'), $access)) {
            return $this->resErr(['验证码已过期，请刷新页面重试'], 401);
        }

        $nickname = $request->get('nickname');
        $zone = $this->createUserZone($nickname);
        $arr = [
            'nickname' => $nickname,
            'password' => $request->get('secret'),
            'zone' => $zone
        ];

        $data = $request->get('method') === 'phone'
            ? array_merge($arr, ['phone' => $request->get('access')])
            : array_merge($arr, ['email' => $request->get('access')]);

        $user = User::create($data);

        return $this->resOK(JWTAuth::fromUser($user));
    }

    /**
     * 用户登录
     *
     * 通过邮箱或手机号登录.
     *
     * @Post("/door/login")
     *
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "secret": "密码"}),
     *      @Response(200, body={"id": 10, "username": "foo"}),
     *      @Response(401, body={"error": "用户名或密码错误"})
     * })
     */
    public function login(Request $request)
    {
        $data = $request->get('method') === 'phone'
            ? [
                'password' => $request->get('secret'),
                'phone' => $request->get('access')
            ]
            : [
                'password' => $request->get('secret'),
                'email' => $request->get('access')
            ];

        if (Auth::attempt($data, $request->get('remember')))
        {
            $user = Auth::user();

            return $this->resOK(JWTAuth::fromUser($user));
        }

        return $this->resErr(['用户名或密码错误'], 401);
    }

    public function logout()
    {
        Auth::logout();

        return $this->resOK();
    }

    public function refresh()
    {
        $user = $this->getAuthUser()->toArray();
        $imageRepository = new ImageRepository();
        $userRepository = new UserRepository();
        $user['uptoken'] = $imageRepository->uptoken();
        $user['daySign'] = $userRepository->daySigned($user['id']);
        $transformer = new UserTransformer();

        return $this->resOK($transformer->self($user));
    }

    private function checkAccessCanUse($method, $access)
    {
        return in_array($method, ['phone', 'email'])
            ? User::where($method, $access)->count()
            : null;
    }

    private function checkAuthCode($code, $access)
    {
        $confirm = Confirm::whereRaw('code = ? and access = ? and created_at > ?', [$code, $access, Carbon::now()->addDay(-1)])->first();
        if (is_null($confirm)) {
            return true;
        }

        $confirm->delete();
        return false;
    }

    private function makeConfirm($access)
    {
        $token = Confirm::whereRaw('access = ? and created_at > ?', [$access, Carbon::now()->addDay(-1)])->first();
        if ( ! is_null($token)) {
            return $token->code;
        }

        $token = str_random(6);
        Confirm::create(['code' => $token, 'access' => $access]);

        return $token;
    }

    private function createUserZone($name)
    {
        $pinyin = Overtrue::permalink($name);

        $tail = UserZone::where('name', $pinyin)->count();

        if ($tail)
        {
            return $pinyin . '-' . implode('-', str_split(($tail), 2));
        }
        else
        {
            UserZone::create(['name' => $pinyin]);

            return $pinyin;
        }
    }
}
