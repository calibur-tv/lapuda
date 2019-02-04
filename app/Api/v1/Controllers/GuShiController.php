<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Models\CartoonRole;
use Illuminate\Http\Request;

/**
 * @Resource("股市相关接口")
 */
class GuShiController extends Controller
{
    public function show(Request $request, $id)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $role = $cartoonRoleRepository->item($id);
        if (is_null($role))
        {
            return $this->resErrNotFound();
        }
        $cartoonTransformer = new CartoonRoleTransformer();
        $userId = $this->getAuthUserId();

        return $this->resOK($cartoonTransformer->show([
            'bangumi' => null,
            'data' => $role,
            'share_data' => [
                'title' => $role['name'],
                'desc' => $role['intro'],
                'link' => $this->createShareLink('role', $id, $userId),
                'image' => "{$role['avatar']}-share120jpg"
            ]
        ]));
    }

    // 列表
    public function list(Request $request)
    {
        $state = $request->get('state');
        $count = $request->get('count');
        $curPage = $request->get('cur_page') ?: 0;
        $toPage = $request->get('to_page') ?: 1;
        $cartoonRoleRepository = new CartoonRoleRepository();
        if (!in_array($state, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]))
        {
            return $this->resErrBad();
        }

        $ids = $cartoonRoleRepository->RedisList("cartoon_role-gushi-list-{$state}", function () use ($state)
        {
            return CartoonRole
                ::where('state', $state)
                ->orderBy('created_at', 'DESC')
                ->pluck('id')
                ->toArray();
        });

        $idsObj = $cartoonRoleRepository->filterIdsByPage($ids, $curPage, ($toPage - $curPage) * $count);
        $list = $cartoonRoleRepository->list($idsObj['ids']);

        return $this->resOK([
            'list' => $list,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }
}
