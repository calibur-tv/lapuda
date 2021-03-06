<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/11/24
 * Time: 下午4:09
 */

namespace App\Api\V1\Services\Counter;


use App\Api\V1\Services\Counter\Base\RelationCounterService;
use App\Models\CartoonRoleFans;

class CartoonRoleStarCounter extends RelationCounterService
{
    public function __construct()
    {
        parent::__construct('cartoon_role_fans', 'star_count');
    }

    public function migration($id)
    {
        return floatval(CartoonRoleFans
            ::where('role_id', $id)
            ->sum('star_count'));
    }
}