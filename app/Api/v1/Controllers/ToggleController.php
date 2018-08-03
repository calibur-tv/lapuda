<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/12
 * Time: 下午9:44
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Toggle\Image\ImageMarkService;
use App\Api\V1\Services\Toggle\Image\ImageRewardService;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Services\Toggle\Post\PostRewardService;
use App\Api\V1\Services\Toggle\Score\ScoreLikeService;
use App\Api\V1\Services\Toggle\Score\ScoreMarkService;
use App\Api\V1\Services\Toggle\Score\ScoreRewardService;
use App\Models\User;
use Illuminate\Http\Request;

class ToggleController extends Controller
{
    public function check(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $userId = $this->getAuthUserId();

        $likeService = $this->getLikeServiceByType($type);
        if (is_null($likeService))
        {
            return $this->resErrBad();
        }

        return $this->resOK($likeService->check($userId, $id));
    }

    public function mixinCheck(Request $request, $type)
    {
        $id = $request->get('id');
        $model = $request->get('model');
        $userId = $this->getAuthUserId();

        if ($type === 'like')
        {
            $service = $this->getLikeServiceByType($model);
        }
        else if ($type === 'follow')
        {
            $service = $this->getFollowServiceByType($model);
        }
        else
        {
            $service = null;
        }

        if (is_null($service))
        {
            return $this->resErrBad();
        }

        return $this->resOK($service->check($userId, $id));
    }

    public function mixinUsers(Request $request, $type)
    {
        $id = $request->get('id');
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 10;
        $model = $request->get('model');

        if ($type === 'like')
        {
            $service = $this->getLikeServiceByType($model);
        }
        else if ($type === 'follow')
        {
            $service = $this->getFollowServiceByType($model);
        }
        else if ($type === 'contributors')
        {
            $service = $this->getContributorsServiceByType($model);
        }
        else
        {
            $service = null;
        }

        if (is_null($service))
        {
            return $this->resErrBad();
        }

        $users = $service->users($id, $page, $take);
        $total = $service->total($id);
        $noMore = $total === 0 ? true : ($total - (($page + 1) * $take) <= 0);

        return $this->resOK([
            'list' => $users,
            'noMore' => $noMore,
            'total' => $total === 0 ? count($users) : $total
        ]);
    }

    public function like(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $userId = $this->getAuthUserId();

        $likeService = $this->getLikeServiceByType($type);
        if (is_null($likeService))
        {
            return $this->resErrBad();
        }

        $result = $likeService->toggle($userId, $id);

        return $this->resCreated((boolean)$result);
    }

    public function follow(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $userId = $this->getAuthUserId();

        $followService = $this->getFollowServiceByType($type);
        if (is_null($followService))
        {
            return $this->resErrBad();
        }

        $check = $followService->beforeHook($id, $userId);
        if ($check !== true)
        {
            return $this->resErrRole($check);
        }

        $result = $followService->toggle($userId, $id);

        return $this->resCreated((boolean)$result);
    }

    public function mark(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $userId = $this->getAuthUserId();

        $markService = $this->getMarkServiceByType($type);
        if (is_null($markService))
        {
            return $this->resErrBad();
        }

        $result = $markService->toggle($userId, $id);
        return $this->resCreated((boolean)$result);
    }

    public function reward(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $userId = $this->getAuthUserId();

        $rewardService = $this->getRewardServiceByType($type);
        if (is_null($rewardService))
        {
            return $this->resErrBad();
        }

        $repository = $this->getRepositoryByType($type);
        if (is_null($repository))
        {
            return $this->resErrBad();
        }

        $item = $repository->item($id);
        if (is_null($item))
        {
            return $this->resErrNotFound();
        }

        if (!$item['is_creator'])
        {
            return $this->resErrBad('该内容非原创');
        }

        if ($item['user_id'] == $userId)
        {
            return $this->resErrBad('不能打赏自己的内容');
        }

        $userRepository = new UserRepository();
        $coinType = $userRepository->getCoinIdByType($type);
        if ($coinType < 0)
        {
            return $this->resErrBad('不支持该类型内容的打赏');
        }

        $rewarded = $rewardService->check($userId, $id);
        if (!$rewarded)
        {
            $userCoin = User::where('id', $userId)
                ->pluck('coin_count')
                ->first();

            if (!$userCoin)
            {
                return $this->resErrRole('金币不足');
            }
        }

        $result = $userRepository->toggleCoin($rewarded, $userId, $item['user_id'], $coinType, $item['id']);
        if (!$result)
        {
            return $this->resErrRole($rewarded ? '未打赏过' : '金币不足');
        }

        $rewardId = $rewardService->toggle($userId, $id);

        return $this->resCreated((boolean)$rewardId);
    }

    public function usersLikeList()
    {

    }

    public function usersMarkList()
    {

    }

    public function usersRewardList()
    {

    }

    public function usersFollowList()
    {

    }

    protected function getContributorsServiceByType($type)
    {
        switch ($type)
        {
            case 'bangumi':
                return new BangumiManager();
                break;
            case 'question':
                return null;
            case 'word':
                return null;
            default:
                return null;
        }
    }

    protected function getFollowServiceByType($type)
    {
        switch ($type)
        {
            case 'bangumi':
                return new BangumiFollowService();
                break;
            case 'user':
                return null;
                break;
            case 'question':
                return null;
                break;
            default:
                return null;
        }
    }

    protected function getMarkServiceByType($type)
    {
        switch ($type)
        {
            case 'post':
                return new PostMarkService();
                break;
            case 'image':
                return new ImageMarkService();
                break;
            case 'score':
                return new ScoreMarkService();
                break;
            default:
                return null;
        }
    }

    protected function getRewardServiceByType($type)
    {
        switch ($type)
        {
            case 'post':
                return new PostRewardService();
                break;
            case 'image':
                return new ImageRewardService();
                break;
            case 'score':
                return new ScoreRewardService();
                break;
            default:
                return null;
        }
    }

    protected function getLikeServiceByType($type)
    {
        switch ($type)
        {
            case 'post':
                return new PostLikeService();
                break;
            case 'image':
                return new ImageLikeService();
                break;
            case 'score':
                return new ScoreLikeService();
                break;
            default:
                return null;
        }
    }

    protected function getRepositoryByType($type)
    {
        switch ($type)
        {
            case 'post':
                return new PostRepository();
                break;
            case 'image':
                return new ImageRepository();
                break;
            case 'score':
                return new ScoreRepository();
                break;
            default:
                return null;
        }
    }
}