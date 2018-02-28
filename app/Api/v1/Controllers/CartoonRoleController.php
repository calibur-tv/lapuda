<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use Illuminate\Http\Request;

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

        // check has follow
        // change table and cache
        return $this->resNoContent();
    }

    public function trending()
    {

    }

    public function fans()
    {

    }
}
