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
use App\Services\Sms\Message;
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
    /**
     * 发送验证码
     *
     * @Post("/door/send")
     *
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "nickname": "用户昵称", "mustNew": "必须是未注册的用户", "mustOld": "必须是已注册的用户", "geetest": "Geetest验证码对象"}),
     *      @Response(201, body={"code": 0, "data": "邮件或短信发送成功"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误", "data": "错误详情"}),
     *      @Response(400, body={"code": 40004, "message": "已注册或未注册的账号", "data": ""})
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
            $sms = new Message();
            $result = $sms->register($access, $token);

            if (!$result)
            {
                return $this->resErrServiceUnavailable();
            }
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
     *      @Response(400, body={"code": 400, "message": "请求参数错误", "data": "错误详情"}),
     *      @Response(401, body={"code": 401, "message": "验证码过期，请重新获取", "data": ""}),
     *      @Response(403, body={"code": 403, "message": "该手机或邮箱已绑定另外一个账号", "data": ""})
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

        if ($isEmail)
        {
            $validator = Validator::make($request->all(), [
                'access' => 'email'
            ]);
        }
        else
        {
            $validator = Validator::make($request->all(), [
                'access' => 'digits:11'
            ]);
        }

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

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
           'invite_code' => $this->convertInviteCode($userId)
        ]);

        $inviteCode = $request->get('inviteCode');
        if ($inviteCode)
        {
            $job = (new \App\Jobs\User\Invite($userId, $inviteCode));
            dispatch($job);
        }

        $job = (new \App\Jobs\Search\User\Register($userId));
        dispatch($job);

        return $this->resCreated($this->responseUser($user));
    }

    /**
     * 用户登录
     *
     * @Post("/door/login")
     * @Transaction({
     *      @Request({"method": "phone|email", "access": "账号", "secret": "密码", "geetest": "Geetest验证码对象"}),
     *      @Response(200, body={"code": 0, "data": "JWT-Token"}),
     *      @Response(400, body={"code": 400, "message": "请求参数错误", "data": "错误详情"}),
     *      @Response(403, body={"code": 403, "message": "用户名或密码错误", "data": ""})
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

            return $this->resOK($this->responseUser($user));
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
     *      @Response(401, body={"code": 40102, "message": "登录超时，请重新登录", "data": ""}),
     *      @Response(401, body={"code": 40103, "message": "登录凭证错误，请重新登录", "data": ""})
     * })
     */
    public function refresh()
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return response([
                'code' => 40103,
                'message' => config('error.40103'),
                'data' => ''
            ], 401);
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
     *      @Response(400, body={"code": 400, "message": "请求参数错误", "data": "错误详情"}),
     *      @Response(403, body={"code": 403, "message": "未注册的邮箱或手机号", "data": ""})
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

        if ($isEmail)
        {
            $validator = Validator::make($request->all(), [
                'access' => 'email'
            ]);
        }
        else
        {
            $validator = Validator::make($request->all(), [
                'access' => 'digits:11'
            ]);
        }

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

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
            $sms = new Message();
            $result = $sms->forgotPassword($access, $token);

            if (!$result)
            {
                return $this->resErrServiceUnavailable();
            }
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
     *      @Response(400, body={"code": 400, "message": "请求参数错误", "data": "错误详情"}),
     *      @Response(403, body={"code": 403, "message": "密码重置成功", "data": ""})
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

        if ($isEmail)
        {
            $validator = Validator::make($request->all(), [
                'access' => 'email'
            ]);
        }
        else
        {
            $validator = Validator::make($request->all(), [
                'access' => 'digits:11'
            ]);
        }

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        if (!$this->authCodeCanUse($request->get('authCode'), $access))
        {
            return $this->resErrBad($isEmail ? '邮箱验证码过期，请重新获取' : '短信认证码过期，请重新获取');
        }

        $time = time();
        $remember_token = md5($time);

        User::where($method, $access)
            ->update([
                'password' => bcrypt($request->get('secret')),
                'password_change_at' => $time,
                'remember_token' => $remember_token
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
        $token = Confirm::whereRaw('access = ? and created_at > ?', [$access, Carbon::now()->addMinutes(-5)])->first();
        if ( ! is_null($token)) {
            return $token->code;
        }

        $token = rand(100000, 999999);
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

    private function responseUser($user)
    {
        return JWTAuth::fromUser($user, [
            'remember' => $user->remember_token
        ]);
    }
}
