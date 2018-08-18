<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Api\V1\Repositories;

use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;

class CartoonRoleRepository extends Repository
{
    public function item($id)
    {
        if (!$id)
        {
            return null;
        }

        return $this->RedisHash('cartoon_role_' . $id, function () use ($id)
        {
           $role = CartoonRole::find($id);

           if (is_null($role))
           {
               return null;
           }

           $userId = CartoonRoleFans::where('role_id', $id)
               ->orderBy('star_count', 'DESC')
               ->pluck('user_id')
               ->first();
           $role->loverId = is_null($userId) ? 0 : intval($userId);

           return $role->toArray();
        }, 'h');
    }

    public function checkHasStar($roleId, $userId)
    {
        if (!$userId)
        {
            return 0;
        }

        $count = CartoonRoleFans::whereRaw('role_id = ? and user_id = ?', [$roleId, $userId])->pluck('star_count')->first();

        return is_null($count) ? 0 : intval($count);
    }

    public function newFansIds($roleId, $minId, $count = null)
    {
        $take = $count ?: config('website.list_count');

        $ids = $this->RedisSort('cartoon_role_' . $roleId . '_new_fans_ids', function () use ($roleId)
        {
            return CartoonRoleFans::where('role_id', $roleId)
                ->orderBy('updated_at', 'desc')
                ->latest()
                ->take(100)
                ->pluck('updated_at', 'user_id AS id');

        }, true, true);

        return $this->filterIdsByMaxId($ids, $minId, $take, true);
    }

    public function hotFansIds($roleId, $seenIds)
    {
        $ids = $this->RedisSort('cartoon_role_' . $roleId . '_hot_fans_ids', function () use ($roleId)
        {
            return CartoonRoleFans::where('role_id', $roleId)
                ->orderBy('star_count', 'desc')
                ->latest()
                ->take(100)
                ->pluck('star_count', 'user_id AS id');

        }, false, true);

        return $this->filterIdsBySeenIds($ids, $seenIds, config('website.list_count'), true);
    }
}