<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\UserTransformer;
use App\Mail\ForgetPassword;
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
 * @Resource("认证相关接口")
 */
class DoorController extends Controller
{
    public function __construct()
    {
        $this->middleware('geetest')->only([
            'login',
            'register',
            'sendEmailOrMessage',
            'forgotPassword',
            'resetPassword'
        ]);
    }

    /**
     * 发送验证码
     *
     * @Post("/door/send")
     *
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "nickname": "用户昵称", "mustNew": "必须是未注册的用户", "mustOld": "必须是已注册的用户", "geetest": "Geetest验证码对象"}),
     *      @Response(201, body={"code": 0, "data": "邮件或短信发送成功"}),
     *      @Response(400, body={"code": 40003, "data": "请求参数错误"}),
     *      @Response(400, body={"code": 40004, "data": "已注册或未注册的账号"})
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
            return $this->resErrParams($validator->errors());
        }

        $method = $request->get('method');
        $access = $request->get('access');
        $nickname = $request->get('nickname');
        $museNew = $request->get('mustNew');
        $mustOld = $request->get('mustOld');

        $isEmail = $method === 'email';

        if ($museNew && !$this->accessIsNew($method, $access))
        {
            return $this->resErrBad($isEmail ? '邮箱已注册' : '手机号已注册');
        }

        if ($mustOld && $this->accessIsNew($method, $access))
        {
            return $this->resErrBad($isEmail ? '未注册的邮箱' : '未注册的手机号');
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

        return $this->resCreated($isEmail ? '邮件已发送' : '短信已发送');
    }

    /**
     * 用户注册
     *
     * @Post("/door/register")
     *
     * @Transaction({
     *      @Request({"method": "phone|email", "nickname": "用户昵称", "access": "账号", "secret": "密码", "authCode": "短信或邮箱验证码", "inviteCode": "邀请码", "geetest": "Geetest验证码对象"}),
     *      @Response(200, body={"code": 0, "data": "JWT-Token"}),
     *      @Response(400, body={"code": 400, "data": "请求参数错误"}),
     *      @Response(401, body={"code": 401, "data": "验证码过期，请重新获取"}),
     *      @Response(403, body={"code": 403, "data": "该手机或邮箱已绑定另外一个账号"})
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
            return $this->resErrParams($validator->errors());
        }

        if (!preg_match('/^([a-zA-Z]+|[0-9]+|[\x{4e00}-\x{9fa5}]+)$/u', $request->get('nickname')))
        {
            return $this->resErrParams('昵称只能包含汉字、数字和字母');
        }

        $method = $request->get('method');
        $access = $request->get('access');
        $isEmail = $method === 'email';

        if (!$this->accessIsNew($method, $access))
        {
            return $this->resErrBad($isEmail ? '该邮箱已注册' : '该手机号已绑定另外一个账号');
        }

        if (!$this->authCodeCanUse($request->get('authCode'), $access))
        {
            return $this->resErrBad($isEmail ? '邮箱验证码过期，请重新获取' : '短信认证码过期，请重新获取');
        }

        $nickname = $request->get('nickname');
        $zone = $this->createUserZone($nickname);
        $arr = [
            'nickname' => $nickname,
            'password' => bcrypt($request->get('secret')),
            'zone' => $zone
        ];

        $data = $isEmail
            ? array_merge($arr, ['email' => $request->get('access')])
            : array_merge($arr, ['phone' => $request->get('access')]);

        $user = User::create($data);
        $userId = $user->id;

        User::where('id', $userId)->update([
           'inviteCode' => $this->convertInviteCode($userId)
        ]);

        $inviteCode = $request->get('inviteCode');
        if ($inviteCode)
        {
            $inviteUserId = User::where('id', $this->convertInviteCode($inviteCode, false))->pluck('id')->first();
            if ($inviteUserId)
            {
                $userRepository = new UserRepository();
                $userRepository->toggleCoin(false, $userId, $inviteUserId, 2, 0);
            }
            // TODO：send some message
        }

        return $this->resCreated(JWTAuth::fromUser($user));
    }

    /**
     * 用户登录
     *
     * @Post("/door/login")
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "secret": "密码", "geetest": "Geetest验证码对象"}),
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
            'secret' => 'required|min:6|max:16'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
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

        if (Auth::attempt($data))
        {
            $user = Auth::user();

            return $this->resOK(JWTAuth::fromUser($user));
        }

        return $this->resErrBad('用户名或密码错误');
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

        return $this->resNoContent();
    }

    /**
     * 获取用户信息
     *
     * 每次启动应用或登录/注册成功后调用
     *
     * @Post("/door/user")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "用户对象"}),
     *      @Response(401, body={"code": 40102, "data": "登录超时，请重新登录"}),
     *      @Response(401, body={"code": 40103, "data": "登录凭证错误，请重新登录"})
     * })
     */
    public function refresh()
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return null;
        }

        $user = $user->toArray();
        $imageRepository = new ImageRepository();
        $userRepository = new UserRepository();
        $user['uptoken'] = $imageRepository->uptoken();
        $user['daySign'] = $userRepository->daySigned($user['id']);
        $user['notification'] = $userRepository->getNotificationCount($user['id']);
        $transformer = new UserTransformer();

        return $this->resOK($transformer->self($user));
    }

    /**
     * 发送重置密码验证码
     *
     * @Post("/door/forgot")
     *
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "geetest": "Geetest验证码对象"}),
     *      @Response(200, body={"code": 0, "data": "短信或邮件已发送"}),
     *      @Response(400, body={"code": 400, "data": "请求参数错误"}),
     *      @Response(403, body={"code": 403, "data": "未注册的邮箱或手机号"})
     * })
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method' => [
                'required',
                Rule::in(['email', 'phone']),
            ],
            'access' => 'required'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $method = $request->get('method');
        $access = $request->get('access');
        $isEmail = $method === 'email';

        if ($this->accessIsNew($method, $access))
        {
            return $this->resErrBad($isEmail ? '未注册的邮箱' : '未注册的手机号');
        }

        $token = $this->makeConfirm($access);

        if ($isEmail)
        {
            Mail::send(new ForgetPassword($access, $token));
        }
        else
        {
            // TODO: send phone message
        }

        return $this->resCreated($isEmail ? '邮件已发送' : '短信已发送');
    }

    /**
     * 重置密码
     *
     * @Post("/door/reset")
     *
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "secret": "密码", "authCode": "短信或邮箱验证码", "geetest": "Geetest验证码对象"}),
     *      @Response(200, body={"code": 0, "data": "短信或邮件已发送"}),
     *      @Response(400, body={"code": 400, "data": "请求参数错误"}),
     *      @Response(403, body={"code": 403, "data": "密码重置成功"})
     * })
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method' => [
                'required',
                Rule::in(['email', 'phone']),
            ],
            'access' => 'required',
            'secret' => 'required|min:6|max:16',
            'authCode' => 'required|min:6|max:6'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $method = $request->get('method');
        $access = $request->get('access');
        $isEmail = $method === 'email';

        if (!$this->authCodeCanUse($request->get('authCode'), $access))
        {
            return $this->resErrBad($isEmail ? '邮箱验证码过期，请重新获取' : '短信认证码过期，请重新获取');
        }

        User::where($method, $access)
            ->update([
                'password' => bcrypt($request->get('secret'))
            ]);

        return $this->resOK('密码重置成功');
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
        $pinyin = strtolower(Overtrue::permalink($name));

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

    private function convertInviteCode($id, $convert = true)
    {
        return $convert
            ? base_convert($id * 1000 + rand(0, 999), 10, 36)
            : intval(base_convert($id, 36, 10) / 1000);
    }
}
