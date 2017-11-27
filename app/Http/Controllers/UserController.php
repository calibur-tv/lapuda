<?php
/**
 * Created by PhpStorm.
 * User: 23002
 * Date: 2017/11/25
 * Time: 0:20
 */

namespace App\Http\Controllers;

use App\Models\User;


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
}