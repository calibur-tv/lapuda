<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/25
 * Time: 上午7:53
 */

namespace App\Api\V1\Services\Trending;


use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Models\CartoonRole;

class RoleTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($bangumiId = 0, $userId = 0)
    {
        parent::__construct('cartoon_role', $bangumiId, $userId);
    }

    public function computeHotIds()
    {
        return CartoonRole
            ::orderBy('star_count', 'desc')
            ->when($this->bangumiId, function ($query)
            {
                return $query->where('bangumi_id', $this->bangumiId);
            })
            ->latest()
            ->take(100)
            ->pluck('star_count', 'id');
    }

    public function getListByIds($ids)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $cartoonRoleTransformer = new CartoonRoleTransformer();

        $result = [];
        foreach ($ids as $id)
        {
            $role = $cartoonRoleRepository->trendingItem($id);
            if (is_null($role))
            {
                continue;
            }
            $result[] = $role;
        }

        return $cartoonRoleTransformer->trending($result);
    }
}