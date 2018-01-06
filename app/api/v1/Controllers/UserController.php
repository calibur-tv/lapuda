<?php
/**
 * Created by PhpStorm.
 * User: 23002
 * Date: 2017/11/25
 * Time: 0:20
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Requests\User\SettingsRequest;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Feedback;
use App\Models\Post;
use App\Models\User;
use App\Api\V1\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
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

        $key = $request->get('type');
        $val = $request->get('url');

        $user->update([
            $key => $val
        ]);

        $cache = 'user_'.$user->id.'_show';
        if (Redis::EXISTS($cache))
        {
            Redis::HSET($cache, $key, $val);
        }

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
        $transformer = new UserTransformer();
        $user = $repository->item($userId);

        return $this->resOK($transformer->show($user));
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

        Redis::DEL('user_'.$user->id);

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

    public function postsOfMine(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErr(['找不到用户'], 404);
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $userRepository = new UserRepository();
        $ids = $userRepository->minePostIds($userId);

        if (empty($ids))
        {
            return $this->resOK();
        }

        $postRepository = new PostRepository();
        $postTransformer = new PostTransformer();
        $list = $postRepository->list(array_slice(array_diff($ids, $seen), 0, $take));

        return $this->resOK($postTransformer->usersMine($list));
    }

    public function postsOfReply(Request $request, $zone)
    {
        $userId = User::where('zone', $zone)->pluck('id')->first();
        if (is_null($userId))
        {
            return $this->resErr(['找不到用户'], 404);
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $userRepository = new UserRepository();
        $ids = $userRepository->replyPostIds($userId);

        if (empty($ids))
        {
            return $this->resOK();
        }

        $ids = array_slice(array_diff($ids, $seen), 0, $take);
        $data = [];
        foreach ($ids as $id)
        {
            $data[] = $userRepository->replyPostItem($userId, $id);
        }

        return $this->resOK($data);
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