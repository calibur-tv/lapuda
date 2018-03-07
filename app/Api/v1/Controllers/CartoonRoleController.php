<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Bangumi;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * @Resource("动漫角色相关接口")
 */
class CartoonRoleController extends Controller
{
    /**
     * 获取番剧角色列表
     *
     * @Get("/bangumi/${bangumiId}/roles")
     *
     * @Parameters({
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "角色列表"}),
     *      @Response(404, body={"code": 40003, "message": "不存在的番剧", "data": ""})
     * })
     */
    public function listOrBangumi(Request $request, $bangumiId)
    {
        if (!Bangumi::where('id', $bangumiId)->count())
        {
            return $this->resErrNotFound('不存在的番剧');
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];

        $cartoonRoleRepository = new CartoonRoleRepository();
        $ids = array_slice(array_diff($cartoonRoleRepository->bangumiOfIds($bangumiId), $seen), 0, config('website.list_count'));

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $roles = $cartoonRoleRepository->list($ids);
        $userRepository = new UserRepository();
        $userId = $this->getAuthUserId();

        foreach ($roles as $i => $item)
        {
            if ($item['loverId'])
            {
                $roles[$i]['lover'] = $userRepository->item($item['loverId']);
                $roles[$i]['loveMe'] = $userId === intval($item['loverId']);
            }
            else
            {
                $roles[$i]['lover'] = null;
                $roles[$i]['loveMe'] = false;
            }
            $roles[$i]['hasStar'] = $userId ? $cartoonRoleRepository->checkHasStar($item['id'], $userId) : 0;
        }

        $transformer = new CartoonRoleTransformer();

        return $this->resOK($transformer->bangumi($roles));
    }

    public function star(Request $request, $bangumiId, $roleId)
    {
        $userId = $this->getAuthUserId();
        $userRepository = new UserRepository();

        if (!$userRepository->toggleCoin(false, $userId, 0, 3, $roleId))
        {
            return $this->resErrRole('没有足够的金币');
        }

        $cartoonRoleRepository = new CartoonRoleRepository();
        if ($cartoonRoleRepository->checkHasStar($roleId, $userId))
        {
            CartoonRoleFans::whereRaw('role_id = ? and user_id = ?', [$roleId, $userId])->increment('star_count');
            $trendingKey = 'cartoon_role_trending_' . $roleId;
            $hotCacheKey = 'cartoon_role_' . $roleId . '_hot_fans_ids';
            if (Redis::EXISTS($hotCacheKey))
            {
                Redis::ZINCRBY($hotCacheKey, 1, $userId);
            }
            if (Redis::EXISTS('cartoon_role_'.$roleId))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$roleId, 'star_count', 1);
            }
            if (Redis::EXISTS($trendingKey))
            {
                Redis::HINCRBYFLOAT($trendingKey, 'star_count', 1);
            }
        }
        else
        {
            CartoonRoleFans::create([
                'role_id' => $roleId,
                'user_id' => $userId,
                'star_count' => 1
            ]);

            CartoonRole::where('id', $roleId)->increment('fans_count');

            $newCacheKey = 'cartoon_role_' . $roleId . '_new_fans_ids';
            $hotCacheKey = 'cartoon_role_' . $roleId . '_hot_fans_ids';
            $trendingKey = 'cartoon_role_trending_' . $roleId;
            if (Redis::EXISTS($newCacheKey))
            {
                Redis::ZADD($newCacheKey, strtotime('now'), $userId);
            }
            if (Redis::EXISTS($hotCacheKey))
            {
                Redis::ZADD($hotCacheKey, 1, $userId);
            }
            if (Redis::EXISTS('cartoon_role_'.$roleId))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$roleId, 'fans_count', 1);
                Redis::HINCRBYFLOAT('cartoon_role_'.$roleId, 'star_count', 1);
            }
            if (Redis::EXISTS($trendingKey))
            {
                Redis::HINCRBYFLOAT($trendingKey, 'fans_count', 1);
                Redis::HINCRBYFLOAT($trendingKey, 'star_count', 1);
            }
        }
        CartoonRole::where('id', $roleId)->increment('star_count');

        return $this->resNoContent();
    }

    public function fans(Request $request, $bangumiId, $roleId)
    {
        $sort = $request->get('sort') ?: 'new';
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $minId = $request->get('minId') ?: 0;
        $cartoonRoleRepository = new CartoonRoleRepository();
        $ids = $sort === 'new' ? $cartoonRoleRepository->newFansIds($roleId, $minId) : $cartoonRoleRepository->hotFansIds($roleId, $seen);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $userRepository = new UserRepository();
        $users = [];
        $i = 0;
        foreach ($ids as $id => $score)
        {
            $users[] = $userRepository->item($id);
            $users[$i]['score'] = $score;
            $i++;
        }

        $transformer = new CartoonRoleTransformer();

        return $this->resOK($transformer->fans($users));
    }
}
