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
        return $this->RedisHash('cartoon_role_' . $id, function () use ($id)
        {
           $role = CartoonRole::find($id);

           if (is_null($role))
           {
               return null;
           }

           $userId = CartoonRoleFans::where('role_id', $id)->orderBy('star_count', 'DESC')->pluck('user_id')->first();
           $role->loverId = !is_null($userId) && count($userId) <= 3 ? intval($userId[0]) : 0;

           return $role->toArray();
        });
    }

    public function list($ids)
    {
        if (empty($ids))
        {
            return [];
        }

        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->item($id);
        }

        return $result;
    }

    public function bangumiOfIds($bangumiId, $page)
    {
        return $this->RedisList('bangumi_' . $bangumiId . 'cartoon_role_page_' . $page, function () use ($bangumiId, $page)
        {
            return CartoonRole::where('bangumi_id', $bangumiId)
                ->take(15)
                ->skip(15 * ($page - 1))
                ->pluck('id');
        });
    }

    public function checkHasStar($roleId, $userId)
    {
        $count = CartoonRoleFans::whereRaw('role_id = ? and user_id = ?', [$roleId, $userId])->pluck('star_count')->first();

        return is_null($count) ? 0 : $count;
    }
}