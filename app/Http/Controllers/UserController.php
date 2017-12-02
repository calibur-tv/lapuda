<?php
/**
 * Created by PhpStorm.
 * User: 23002
 * Date: 2017/11/25
 * Time: 0:20
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;


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
            return $this->resErr(['找不到用户']);
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
    public function avatar(Request $request)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['请刷新页面重试'], 401);
        }
        /* @var User $user*/
        $user->update(['avatar'=>$request->post('avatar')]);
        return $this->resOK();
    }

    public function show(Request $request)
    {
        $zone = $request->get('zone');

        $userId = User::where('zone', $zone)->select('id')->first();
        if (is_null($userId))
        {
            return $this->resErr(['该用户不存在'], 404);
        }

        $repository = new UserRepository();
        $user = $repository->item($userId);

        return $this->resOK($user);
    }
}