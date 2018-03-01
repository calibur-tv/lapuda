<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class CartoonRoleController extends Controller
{
    public function listOrBangumi(Request $request, $bangumiId)
    {
        $page = intval($request->get('page')) ?: 1;

        $cartoonRoleRepository = new CartoonRoleRepository();
        $ids = $cartoonRoleRepository->bangumiOfIds($bangumiId, $page);

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
                $roles[$i]['loveMe'] = $userId === $item['loverId'];
            }
            else
            {
                $roles[$i]['lover'] = null;
                $roles[$i]['loveMe'] = false;
            }
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
            Redis::pipeline(function ($pipe) use ($roleId, $userId)
            {
                $pipe->ZINCRBY('cartoon_role_' . $roleId . '_hot_fans_ids', 1, $userId);
                if ($pipe->EXISTS('cartoon_role_'.$roleId))
                {
                    $pipe->HINCRBYFLOAT('cartoon_role_'.$roleId, 'star_count', 1);
                }
            });
        }
        else
        {
            CartoonRoleFans::create([
                'role_id' => $roleId,
                'user_id' => $userId,
                'star_count' => 1
            ]);

            CartoonRole::where('id', $roleId)->increment('fans_count');

            Redis::pipeline(function ($pipe) use ($roleId, $userId)
            {
                $newCacheKey = 'cartoon_role_' . $roleId . '_new_fans_ids';
                $hotCacheKey = 'cartoon_role_' . $roleId . '_hot_fans_ids';
                if ($pipe->EXISTS($newCacheKey))
                {
                    $pipe->ZADD($newCacheKey, strtotime('now'), $userId);
                }
                if ($pipe->EXISTS($hotCacheKey))
                {
                    $pipe->ZADD($hotCacheKey, 1, $userId);
                }
                if ($pipe->EXISTS('cartoon_role_'.$roleId))
                {
                    $pipe->HINCRBYFLOAT('cartoon_role_'.$roleId, 'fans_count', 1);
                    $pipe->HINCRBYFLOAT('cartoon_role_'.$roleId, 'star_count', 1);
                }
            });
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
        $users = $userRepository->list($ids);

        $transformer = new UserTransformer();

        return $this->resOK($transformer->list($users));
    }
}
