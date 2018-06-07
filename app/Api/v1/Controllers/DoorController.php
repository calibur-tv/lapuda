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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Overtrue\LaravelPinyin\Facades\Pinyin as Overtrue;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @Resource("认证相关接口")
 */
class DoorController extends Controller
{
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => [
                'required',
                Rule::in(['sign_up', 'forgot_password']),
            ],
            'phone_number' => 'required|digits:11'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $phone = $request->get('phone_number');
        $type = $request->get('type');

        if ($type === 'sign_up')
        {
            $museNew = true;
            $mustOld = false;
        }
        else if ($type === 'forgot_password')
        {
            $museNew = false;
            $mustOld = true;
        }
        else
        {
            $museNew = false;
            $mustOld = false;
        }

        if ($museNew && !$this->accessIsNew('phone', $phone))
        {
            return $this->resErrBad('手机号已注册');
        }

        if ($mustOld && $this->accessIsNew('phone', $phone))
        {
            return $this->resErrBad('未注册的手机号');
        }

        $authCode = $this->createMessageAuthCode($phone, $type);
        $sms = new Message();

        if ($type === 'sign_up')
        {
            $result = $sms->register($phone, $authCode);
        }
        else if ($type === 'forgot_password')
        {
            $result = $sms->forgotPassword($phone, $authCode);
        }
        else
        {
            return $this->resErrBad();
        }

        if (!$result)
        {
            return $this->resErrServiceUnavailable();
        }

        return $this->resCreated('短信已发送');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access' => 'required|digits:11',
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

        $access = $request->get('access');

        if (!$this->checkMessageAuthCode($access, 'sign_up', $request->get('authCode')))
        {
            return $this->resErrBad('短信验证码已过期，请重新获取');
        }

        if (!$this->accessIsNew('phone', $access))
        {
            return $this->resErrBad('该手机号已绑定另外一个账号');
        }

        $nickname = $request->get('nickname');
        $zone = $this->createUserZone($nickname);
        $data = [
            'nickname' => $nickname,
            'password' => bcrypt($request->get('secret')),
            'zone' => $zone,
            'phone' => $access
        ];

        try
        {
            $user = User::create($data);
        }
        catch (\Exception $e)
        {
            return $this->resErrBad('昵称暂不可用，请尝试其它昵称');
        }

        $userId = $user->id;

        $inviteCode = $request->get('inviteCode');
        if ($inviteCode)
        {
            $job = (new \App\Jobs\User\Invite($userId, $inviteCode));
            dispatch($job);
        }

        $job = (new \App\Jobs\Search\User\Register($userId));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('user/' . $zone));
        dispatch($job);

        return $this->resCreated($this->responseUser($user));
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access' => 'required|digits:11',
            'secret' => 'required|min:6|max:16'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $data = [
            'phone' => $request->get('access'),
            'password' => $request->get('secret')
        ];

        if (Auth::attempt($data))
        {
            $user = Auth::user();

            $jwtToken = $this->responseUser($user);

            return response([
                'code' => 0,
                'data' => $jwtToken
            ], 200);
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

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access' => 'required|digits:11',
            'secret' => 'required|min:6|max:16',
            'authCode' => 'required|min:6|max:6'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $access = $request->get('access');

        if (!$this->checkMessageAuthCode($access, 'forgot_password', $request->get('authCode')))
        {
            return $this->resErrBad('短信验证码过期，请重新获取');
        }

        $time = time();
        $remember_token = md5($time);

        User::where('phone', $access)
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

    private function createUserZone($name)
    {
        $pinyin = strtolower(Overtrue::permalink($name));

        $tail = UserZone::where('name', $pinyin)->pluck('count')->first();

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

    private function responseUser($user)
    {
        return JWTAuth::fromUser($user, [
            'remember' => $user->remember_token
        ]);
    }

    private function createMessageAuthCode($phone, $type)
    {
        $key = 'phone_message_' . $type . '_' . $phone;
        $value = rand(100000, 999999);

        Redis::SET($key, $value);
        Redis::EXPIRE($key, 300);

        return $value;
    }

    private function checkMessageAuthCode($phone, $type, $token)
    {
        $cache = Redis::GET('phone_message_' . $type . '_' . $phone);
        if (is_null($cache))
        {
            return false;
        }

        return intval($cache) === intval($token);
    }
}
