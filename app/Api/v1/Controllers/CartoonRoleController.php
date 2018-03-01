<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class CartoonRoleController extends Controller
{
    public function listOrBangumi(Request $request, $id)
    {
        $page = intval($request->get('page')) ?: 1;

        $cartoonRoleRepository = new CartoonRoleRepository();
        $ids = $cartoonRoleRepository->bangumiOfIds($id, $page);

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

    public function star(Request $request, $roleId)
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
            // todo cartoonRoleFans cache
        }
        else
        {
            CartoonRoleFans::create([
                'role_id' => $roleId,
                'user_id' => $userId,
                'star_count' => 1
            ]);
            // todo cartoonRoleFans cache
            CartoonRole::where('id', $roleId)->increment('fans_count');

            if (Redis::EXISTS('cartoon_role_'.$roleId))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$roleId, 'fans_count', 1);
            }
        }
        CartoonRole::where('id', $roleId)->increment('star_count');
        if (Redis::EXISTS('cartoon_role_'.$roleId))
        {
            Redis::HINCRBYFLOAT('cartoon_role_'.$roleId, 'star_count', 1);
        }

        return $this->resNoContent();
    }

    public function trending()
    {

    }

    public function fans(Request $request, $roleId)
    {
        $sort = $request->get('sort') ?: 'new';
        $page = $request->get('page') ?: 1;
        // hot 应该用 seenId, new 应该用 maxId
    }
}
