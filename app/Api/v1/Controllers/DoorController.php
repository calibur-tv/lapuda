<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    /**
     * 发送验证码
     *
     * @Post("/door/send")
     *
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "nickname": "用户昵称", "mustNew": "必须是未注册的用户", "mustOld": "必须是已注册的用户", "geetest": "Geetest验证码对象"}),
     *      @Response(200, body={"code": 0, "data": "邮件或短信发送成功"}),
     *      @Response(400, body={"code": 400, "data": "请求参数错误"}),
     *      @Response(403, body={"code": 403, "data": "已注册或未注册的账号"})
     * })
     */
    public function sendEmailOrMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method' => [
                'required',
                Rule::in(['email', 'phone']),
            ],
            'access' => 'required',
            'nickname' => 'required|min:1|max:14',
            'mustNew' => 'boolean',
            'mustOld' => 'boolean'
        ]);

        if ($validator->fails())
        {
            return $this->res('请求参数错误', 400);
        }

        $method = $request->get('method');
        $access = $request->get('access');
        $nickname = $request->get('nickname');
        $museNew = $request->get('mustNew');
        $mustOld = $request->get('mustOld');

        $isEmail = $method === 'email';

        if ($museNew && !$this->accessIsNew($method, $access))
        {
            return $this->res($isEmail ? '邮箱已注册' : '手机号已注册', 403);
        }

        if ($mustOld && $this->accessIsNew($method, $access))
        {
            return $this->res($isEmail ? '未注册的邮箱' : '未注册的手机号', 403);
        }

        $token = $this->makeConfirm($access);

        if ($isEmail)
        {
            Mail::send(new Welcome($access, $nickname, $token));
        }
        else
        {
            // TODO: send phone message
        }

        return $this->res($isEmail ? '邮件已发送' : '短信已发送');
    }

    /**
     * 用户注册
     *
     * @Post("/door/register")
     *
     * @Transaction({
     *      @Request({"method": "phone|email", "nickname": "用户昵称", "access": "账号", "secret": "密码", "authCode": "短信或邮箱验证码", "inviteCode": "邀请码"}),
     *      @Response(200, body={"code": 0, "data": "JWT-Token"}),
     *      @Response(400, body={"code": 400, "data": "请求参数错误"}),
     *      @Response(403, body={"code": 403, "data": "该手机或邮箱已绑定另外一个账号"}),
     *      @Response(401, body={"code": 401, "data": "验证码过期，请重新获取"})
     * })
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method' => [
                'required',
                Rule::in(['email', 'phone']),
            ],
            'access' => 'required',
            'secret' => 'required|min:6|max:16',
            'nickname' => 'required|min:1|max:14',
            'authCode' => 'required|min:6|max:6'
        ]);

        if ($validator->fails())
        {
            return $this->res('请求参数错误', 400);
        }

        $method = $request->get('method');
        $access = $request->get('access');

        if (!$this->accessIsNew($method, $access))
        {
            return $this->res('该手机或邮箱已绑定另外一个账号', 403);
        }

        $isEmail = $method === 'email';
        if (!$this->authCodeCanUse($request->get('authCode'), $access))
        {
            return $this->res($isEmail ? '邮箱验证码过期，请重新获取' : '短信认证码过期，请重新获取', 401);
        }

        $nickname = $request->get('nickname');
        $zone = $this->createUserZone($nickname);
        $arr = [
            'nickname' => $nickname,
            'password' => $request->get('secret'),
            'zone' => $zone
        ];

        $data = $isEmail
            ? array_merge($arr, ['email' => $request->get('access')])
            : array_merge($arr, ['phone' => $request->get('access')]);

        $user = User::create($data);

        return $this->res(JWTAuth::fromUser($user));
    }

    /**
     * 用户登录
     *
     * @Post("/door/login")
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "secret": "密码"}),
     *      @Response(200, body={"code": 0, "data": "JWT-Token"}),
     *      @Response(400, body={"code": 400, "data": "请求参数错误"}),
     *      @Response(403, body={"code": 403, "data": "用户名或密码错误"})
     * })
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method' => [
                'required',
                Rule::in(['email', 'phone']),
            ],
            'access' => 'required',
            'secret' => 'required|min:6|max:16',
            'remember' => 'required|boolean'
        ]);

        if ($validator->fails())
        {
            return $this->res('请求参数错误', 400);
        }

        $method = $request->get('method');
        $access = $request->get('access');
        $secret = $request->get('secret');

        $data = $method === 'phone'
            ? [
                'password' => $secret,
                'phone' => $access
            ]
            : [
                'password' => $secret,
                'email' => $access
            ];

        if (Auth::attempt($data, $request->get('remember')))
        {
            $user = Auth::user();

            return $this->res(JWTAuth::fromUser($user));
        }

        return $this->res('用户名或密码错误', 403);
    }

    /**
     * 用户登出
     *
     * @Post("/door/logout")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     */
    public function logout()
    {
        Auth::logout();

        return $this->res();
    }

    /**
     * 获取用户数据
     *
     * 每次启动应用或登录/注册成功后调用
     *
     * @Post("/door/user")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     * @Transaction({
     *      @Request({"method": "phone|email", "nickname": "用户昵称", "access": "账号", "secret": "密码", "authCode": "短信或邮箱验证码", "inviteCode": "邀请码"}),
     *      @Response(200, body={"code": 0, "data": "用户对象"}),
     *      @Response(401, body={"code": 401, "data": "未登录的用户"}),
     * })
     */
    public function refresh()
    {
        $user = $this->getAuthUser()->toArray();
        $imageRepository = new ImageRepository();
        $userRepository = new UserRepository();
        $user['uptoken'] = $imageRepository->uptoken();
        $user['daySign'] = $userRepository->daySigned($user['id']);
        $transformer = new UserTransformer();

        return $this->res($transformer->self($user));
    }

    private function accessIsNew($method, $access)
    {
        return User::where($method, $access)->count() === 0;
    }

    private function authCodeCanUse($code, $access)
    {
        $confirm = Confirm::whereRaw('code = ? and access = ? and created_at > ?', [$code, $access, Carbon::now()->addDay(-1)])->first();
        if (is_null($confirm)) {
            return false;
        }

        $confirm->delete();
        return true;
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
