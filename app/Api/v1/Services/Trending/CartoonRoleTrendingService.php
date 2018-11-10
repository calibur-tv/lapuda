<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/25
 * Time: 上午7:53
 */

namespace App\Api\V1\Services\Trending;


use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Trending\Base\TrendingService;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use Illuminate\Support\Facades\Redis;

class CartoonRoleTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($bangumiId = 0, $userId = 0)
    {
        parent::__construct('cartoon_role', $bangumiId, $userId);
    }

    public function getHotIds($seenIds, $take)
    {
        // 动态无序，使用 seenIds 的 sort set
        $ids = $this->RedisSort($this->trendingIdsCacheKey('hot', $this->bangumiId), function ()
        {
            return $this->computeHotIds();
        }, false, false, 'm');

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
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

    public function computeUserIds()
    {
        return CartoonRoleFans
            ::where('user_id', $this->userId)
            ->orderBy('updated_at', 'DESC')
            ->pluck('role_id');
    }

    public function users($page, $take)
    {
        $idsObject = $this->getUserIds($page, $take);
        $list = $this->getListByIds($idsObject['ids'], '');

        $cartoonRoleRepository = new CartoonRoleRepository();
        foreach ($list as $i => $item)
        {
            $list[$i]['has_star'] = $cartoonRoleRepository->checkHasStar($item['id'], $this->userId);
        }

        return [
            'list' => $list,
            'noMore' => $idsObject['noMore'],
            'total' => $idsObject['total']
        ];
    }

    public function create($id, $publish = true)
    {
        $this->SortAdd($this->trendingIdsCacheKey('hot', $this->bangumiId), $id);
        // 刷新排行榜
        $this->SortAdd($this->trendingIdsCacheKey('hot', 0), $id, 1);
        // 今日动态榜单
        $this->SortAdd('cartoon_role_today_activity_ids', $id, 1);
        // 删除个人的缓存，因为插入会有重复
        if (!$publish)
        {
            Redis::DEL($this->trendingFlowUsersKey());
        }
    }

    public function getListByIds($ids, $flowType)
    {
        $store = new CartoonRoleRepository();
        $userRepository = new UserRepository();
        $list = $store->userFlow($ids);
        if (empty($list))
        {
            return [];
        }

        foreach ($list as $i => $role)
        {
            $hasLover = intval($role['loverId']);
            $user = $hasLover ? $userRepository->item($role['loverId']) : null;

            if ($hasLover)
            {
                $list[$i]['lover_avatar'] = $user['avatar'];
                $list[$i]['lover_nickname'] = $user['nickname'];
                $list[$i]['lover_zone'] = $user['zone'];
            }
        }

        $transformer = new CartoonRoleTransformer();

        return $transformer->trending($list);
    }
}