<?php
/**
 * Created by PhpStorm.
 * User: 23002
 * Date: 2017/11/25
 * Time: 0:20
 */

namespace App\Http\Controllers;

use App\Http\Requests\User\SettingsRequest;
use App\Models\Feedback;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mews\Purifier\Facades\Purifier;


/**
 * Class UserController
 * @package App\Http\Controllers
 */
class UserController extends Controller
{
    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function getUserSign()
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['找不到用户'], 404);
        }
        /* @var User $user */
        if ($user->isSignToday())
        {
            return $this->resErr(['已签到']);
        }

        $user->signNow();

        return $this->resOK('', '签到成功');
    }

    /**上传头像
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function image(Request $request)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['请刷新页面重试'], 401);
        }
        /* @var User $user*/
        $user->update([
            $request->get('type') => $request->get('url')
        ]);

        return $this->resOK();
    }

    public function show(Request $request)
    {
        $zone = $request->get('zone');

        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErr(['该用户不存在'], 404);
        }

        $repository = new UserRepository();
        $user = $repository->item($userId);

        return $this->resOK($user);
    }

    public function profile(SettingsRequest $request)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['找不到用户'], 404);
        }

        $user->update([
            'nickname' => Purifier::clean($request->get('nickname')),
            'signature' => Purifier::clean($request->get('signature')),
            'sex' => $request->get('sex'),
            'birthday' => $request->get('birthday')
        ]);

        Cache::forget('user_'.$user->id.'_show');

        return $this->resOK();
    }

    public function followedBangumis($zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErr(['找不到用户'], 404);
        }

        $repository = new UserRepository();
        $follows = $repository->bangumis($userId);

        return $this->resOK($follows);
    }

    public function posts(Request $request)
    {
        // TODO：使用 seen_ids 做分页
        // TODO：应该 new 一个 PostRepository，有一个 list 的方法，接收 ids 做参数
    }

    public function feedback(Request $request)
    {
        $user = $this->getAuthUser();
        Feedback::create([
            'type' => $request->get('type'),
            'desc' => $request->get('desc'),
            'user_id' => is_null($user) ? 0 : $user->id
        ]);

        return $this->resOK();
    }
}