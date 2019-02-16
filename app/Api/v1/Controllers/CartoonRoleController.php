<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Activity\BangumiActivity;
use App\Api\V1\Services\Activity\UserActivity;
use App\Api\V1\Services\Counter\CartoonRoleFansCounter;
use App\Api\V1\Services\Counter\CartoonRoleStarCounter;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Tag\Base\UserBadgeService;
use App\Api\V1\Services\Trending\CartoonRoleTrendingService;
use App\Api\V1\Services\VirtualCoinService;
use App\Api\V1\Services\Vote\IdolVoteService;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use App\Models\LightCoinRecord;
use App\Models\VirtualIdolDeal;
use App\Models\VirtualIdolDealRecord;
use App\Models\VirtualIdolOwner;
use App\Models\VirtualIdolPriceDraft;
use App\Services\OpenSearch\Search;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\UserIpAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("偶像相关接口")
 */
class CartoonRoleController extends Controller
{
    /**
     * 给偶像应援
     *
     * @Post("/cartoon_role/`roleId`/star")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     *
     * @Transaction({
     *      @Response(204),
     *      @Response(404, body={"code": 40401, "message": "不存在的偶像"}),
     *      @Response(403, body={"code": 40301, "message": "没有足够的团子"})
     * })
     */
    public function star(Request $request, $id)
    {
        return $this->resErrServiceUnavailable('应援功能暂时关闭');

        $cartoonRoleRepository = new CartoonRoleRepository();
        $cartoonRole = $cartoonRoleRepository->item($id);
        if (is_null($cartoonRole))
        {
            return $this->resErrNotFound();
        }

        $user = $this->getAuthUser();
        $userId = $user->id;
        $userIpAddress = new UserIpAddress();
        $blocked = $userIpAddress->check($userId);
        if ($blocked)
        {
            return $this->resErrRole('你已被封禁，无权应援');
        }

        $virtualCoinService = new VirtualCoinService();
        $banlance = $virtualCoinService->hasMoneyCount($user);
        $amount = $request->get('amount') ?: 1;
        if ($banlance < $amount)
        {
            return $this->resErrRole('没有足够的团子');
        }

        $result = $virtualCoinService->cheerForIdol($userId, $id, $amount);
        if (!$result)
        {
            return $this->resErrServiceUnavailable('系统升级中');
        }

        $cartoonRoleTrendingService = new CartoonRoleTrendingService($cartoonRole['bangumi_id'], $userId);
        $isOldFans = $cartoonRoleRepository->checkHasStar($id, $userId);

        if ($isOldFans)
        {
            CartoonRoleFans
                ::whereRaw('role_id = ? and user_id = ?', [$id, $userId])
                ->increment('star_count', $amount);

            if (Redis::EXISTS('cartoon_role_'.$id))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'star_count', $amount);
            }
        }
        else
        {
            CartoonRoleFans::create([
                'role_id' => $id,
                'user_id' => $userId,
                'star_count' => $amount
            ]);

            $cartoonRoleFansCounter = new CartoonRoleFansCounter();
            $cartoonRoleFansCounter->add($id);

            if (Redis::EXISTS('cartoon_role_'.$id))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'fans_count', 1);
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'star_count', $amount);
            }
        }
        $cartoonRoleTrendingService->update($id, $isOldFans);
        // 今日动态榜单
        $cartoonRoleRepository->SortAdd('cartoon_role_today_activity_ids', $id, $amount);

        $newCacheKey = 'cartoon_role_' . $id . '_new_fans_ids';
        $hotCacheKey = 'cartoon_role_' . $id . '_hot_fans_ids';

        if (Redis::EXISTS($newCacheKey))
        {
            Redis::ZADD($newCacheKey, strtotime('now'), $userId);
        }
        if (Redis::EXISTS($hotCacheKey))
        {
            Redis::ZINCRBY($hotCacheKey, $amount, $userId);
        }

        $cartoonRoleStarCounter = new CartoonRoleStarCounter();
        $cartoonRoleStarCounter->add($id, $amount);

        $userActivityService = new UserActivity();
        $userActivityService->update($userId, 3);

        $bangumiActivityService = new BangumiActivity();
        $bangumiActivityService->update($cartoonRole['bangumi_id']);

        return $this->resOK();
    }

    /**
     * 偶像的粉丝列表
     *
     * @Post("/cartoon_role/`roleId`/fans")
     *
     * 如果是 sort 传入 new，就再传 minId，如果 sort 传入 hot，就再传 seenIds
     *
     */
    public function fans(Request $request, $id)
    {
        if (!CartoonRole::where('id', $id)->count())
        {
            return $this->resErrNotFound();
        }

        $sort = $request->get('sort') ?: 'new';
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $minId = $request->get('minId') ?: 0;
        $cartoonRoleRepository = new CartoonRoleRepository();
        $idsObj = $sort === 'new' ? $cartoonRoleRepository->newFansIds($id, $minId) : $cartoonRoleRepository->hotFansIds($id, $seen);

        $ids = $idsObj['ids'];
        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'total' => 0,
                'noMore' => true
            ]);
        }

        $userRepository = new UserRepository();
        $users = [];
        $i = 0;
        foreach ($ids as $roleId => $score)
        {
            $role = $userRepository->item($roleId);
            if (is_null($role))
            {
                continue;
            }
            $users[] = $userRepository->item($roleId);
            $users[$i]['score'] = $score;
            $i++;
        }

        $transformer = new CartoonRoleTransformer();
        $list = $transformer->fans($users);

        return $this->resOK([
            'list' => $list,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

    /**
     * 偶像详情
     *
     * @Get("/cartoon_role/`roleId`/show")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"bangumi": "番剧简介", "data": "偶像信息"}}),
     *      @Response(404, body={"code": 40401, "message": "不存在的偶像"}),
     * })
     */
    public function show($id)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $role = $cartoonRoleRepository->item($id);
        if (is_null($role))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();

        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->panel($role['bangumi_id'], $userId);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $userRepository = new UserRepository();
        $userTransformer = new UserTransformer();
        $cartoonRoleStarCounter = new CartoonRoleStarCounter();
        $cartoonRoleFansCounter = new CartoonRoleFansCounter();

        $loverId = CartoonRoleFans
            ::where('role_id', $id)
            ->orderBy('star_count', 'DESC')
            ->pluck('user_id')
            ->first();

        $role['lover'] = $loverId ? $userRepository->item($loverId) : null;
        if ($role['lover'])
        {
            $role['lover'] = $userTransformer->item($role['lover']);
            $loverScore = Redis::ZSCORE('cartoon_role_' . $id . '_hot_fans_ids', $loverId);
            $role['lover']['score'] = $loverScore ? intval($loverScore) : 0;
        }
        $role['hasStar'] = $cartoonRoleRepository->checkHasStar($role['id'], $userId);

        $cartoonTransformer = new CartoonRoleTransformer();

        $searchService = new Search();
        if ($searchService->checkNeedMigrate('role', $id))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('role', $id));
            dispatch($job);
        }
        $trending = Redis::ZREVRANK('trending_cartoon_role_bangumi_0_hot_ids', $id);
        $role['star_count'] = $cartoonRoleStarCounter->get($id);
        $role['fans_count'] = $cartoonRoleFansCounter->get($id);
        $role['trending'] = is_null($trending) ? 0 : $trending + 1;

        return $this->resOK($cartoonTransformer->show([
            'bangumi' => $bangumi,
            'data' => $role,
            'share_data' => [
                'title' => $role['name'],
                'desc' => $role['intro'],
                'link' => $this->createShareLink('role', $id, $userId),
                'image' => "{$role['avatar']}-share120jpg"
            ]
        ]));
    }

    // 新偶像详情
    public function stockShow($id)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $role = $cartoonRoleRepository->item($id);
        if (is_null($role))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();

        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->panel($role['bangumi_id'], $userId);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $userRepository = new UserRepository();
        $userTransformer = new UserTransformer();
        $role['boss'] = null;
        $role['manager'] = null;
        if ($role['boss_id'])
        {
            $boss = $userRepository->item($role['boss_id']);
            if ($boss)
            {
                $role['boss'] = $userTransformer->item($boss);
            }
        }
        if ($role['manager_id'])
        {
            $manager = $userRepository->item($role['manager_id']);
            if ($manager)
            {
                $role['manager'] = $userTransformer->item($manager);
            }
        }
        if ($userId)
        {
            $role['has_star'] = VirtualIdolOwner
                ::where('idol_id', $id)
                ->where('user_id', $userId)
                ->pluck('stock_count')
                ->first();
        }
        else
        {
            $role['has_star'] = 0;
        }
        $role['chart'] = $cartoonRoleRepository->idol24HourStockChartData($id);
        $role['has_market_price_draft'] = false;
        $role['market_price_draft_voted'] = 0;
        if ($role['has_star'])
        {
            // 通知
            $draftId = $cartoonRoleRepository->lastIdolMarketPriceDraftId($id);
            if ($draftId != 0)
            {
                $idolVoteService = new IdolVoteService();
                $role['has_market_price_draft'] = true;
                $role['market_price_draft_voted'] = $idolVoteService->check($userId, $draftId);
            }
        }

        $searchService = new Search();
        if ($searchService->checkNeedMigrate('role', $id))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('role', $id));
            dispatch($job);
        }

        $cartoonTransformer = new CartoonRoleTransformer();
        return $this->resOK([
            'bangumi' => $bangumi,
            'role' => $cartoonTransformer->idol($role),
            'share_data' => [
                'title' => $role['name'],
                'desc' => $role['intro'],
                'link' => $this->createShareLink('role', $id, $userId),
                'image' => "{$role['avatar']}-share120jpg"
            ]
        ]);
    }

    // 入股
    public function buyStock(Request $request, $id)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $cartoonRole = $cartoonRoleRepository->item($id);
        if (is_null($cartoonRole))
        {
            return $this->resErrNotFound();
        }

        $user = $this->getAuthUser();
        $userId = $user->id;
        $userIpAddress = new UserIpAddress();
        $blocked = $userIpAddress->check($userId);
        if ($blocked)
        {
            return $this->resErrRole('你已被封禁，无权入股');
        }

        $buyCount = $request->get('amount') ?: 1;
        $maxStarCount = floatval($cartoonRole['max_stock_count']);
        if ($maxStarCount && $maxStarCount == floatval($cartoonRole['star_count']))
        {
            return $this->resErrRole('产品已停牌，无法入股');
        }
        if ($maxStarCount && ($maxStarCount < floatval($cartoonRole['star_count']) + floatval($buyCount)))
        {
            return $this->resErrRole('入股数量超限额，无法购买');
        }

        $virtualCoinService = new VirtualCoinService();
        $balance = $virtualCoinService->hasMoneyCount($user);

        $stockPrice = floatval(CartoonRole
            ::where('id', $id)
            ->pluck('stock_price')
            ->first());
        $payAmount = $this->calculate($buyCount * $stockPrice);

        if ($balance < $payAmount)
        {
            return $this->resErrRole('没有足够的虚拟币');
        }

        $result = $virtualCoinService->cheerForIdol($userId, $id, $payAmount);
        if (!$result)
        {
            return $this->resErrServiceUnavailable('入股失败');
        }

        $oldOwnerData = VirtualIdolOwner
            ::where('idol_id', $id)
            ->where('user_id', $userId)
            ->first();
        $isOldFans = !is_null($oldOwnerData);

        if ($isOldFans)
        {
            // 如果是老粉丝，就更新之前的数据
            VirtualIdolOwner
                ::where('idol_id', $id)
                ->where('user_id', $userId)
                ->increment('total_price', $payAmount);

            VirtualIdolOwner
                ::where('idol_id', $id)
                ->where('user_id', $userId)
                ->increment('stock_count', $buyCount);
        }
        else
        {
            // 如果是新粉丝，就新建数据
            // 并且粉丝数 + 1
            VirtualIdolOwner::create([
                'idol_id' => $id,
                'user_id' => $userId,
                'total_price' => $payAmount,
                'stock_count' => $buyCount
            ]);

            CartoonRole
                ::where('id', $id)
                ->increment('fans_count');
        }

        // 更新市值和发行的股数
        CartoonRole
            ::where('id', $id)
            ->increment('market_price', $payAmount);
        CartoonRole
            ::where('id', $id)
            ->increment('star_count', $buyCount);

        // 上市
        $doIPO = !$isOldFans && intval($cartoonRole['fans_count']) >= 19 && $cartoonRole['company_state'] == 0;
        if ($doIPO)
        {
            CartoonRole
                ::where('id', $id)
                ->update([
                    'company_state' => 1,
                    'ipo_at' => Carbon::now()
                ]);

            $cartoonRoleRepository->setIdolBiggestBoss($id);
        }

        $currentTime = strtotime('now');
        // 更新新股东列表
        $cacheKey = $cartoonRoleRepository->newOwnerIdsCacheKey($id);
        if (Redis::EXISTS($cacheKey))
        {
            Redis::ZADD($cacheKey, $currentTime, $userId);
        }
        // 更新大股东列表
        $cacheKey = $cartoonRoleRepository->bigOwnerIdsCacheKey($id);
        if (Redis::EXISTS($cacheKey))
        {
            if ($isOldFans)
            {
                Redis::ZADD($cacheKey, $oldOwnerData->stock_count + $buyCount, $userId);
            }
            else
            {
                Redis::ZADD($cacheKey, $buyCount, $userId);
            }
        }
        // 更新用户的缓存列表
        $cacheKey = $cartoonRoleRepository->userIdolListCacheKey($userId);
        if (Redis::EXISTS($cacheKey))
        {
            if ($isOldFans)
            {
                Redis::ZADD($cacheKey, $oldOwnerData->stock_count + $buyCount, $id);
            }
            else
            {
                Redis::ZADD($cacheKey, $buyCount, $id);
            }
        }
        // 更新偶像缓存
        $cacheKey = $cartoonRoleRepository->idolItemCacheKey($id);
        if (Redis::EXISTS($cacheKey))
        {
            Redis::HINCRBYFLOAT($cacheKey, 'star_count', $buyCount);
            Redis::HINCRBYFLOAT($cacheKey, 'market_price', $payAmount);
            if (!$isOldFans)
            {
                Redis::HINCRBYFLOAT($cacheKey, 'fans_count', 1);
            }
            if ($doIPO)
            {
                Redis::HSET($cacheKey, 'company_state', 1);
                Redis::HSET($cacheKey, 'ipo_at', Carbon::now());
            }
        }
        if ($doIPO)
        {
            $cacheKey = $cartoonRoleRepository->newbieIdolListCacheKey('newest');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZREM($cacheKey, $id);
            }
            $cacheKey = $cartoonRoleRepository->newbieIdolListCacheKey('star_count');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZREM($cacheKey, $id);
            }
        }
        if ($cartoonRole['company_state'] == 1 || $doIPO)
        {
            // 更新公司列表
            $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('activity');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZADD($cacheKey, $currentTime, $id);
            }
            $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('newest');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZADD($cacheKey, $currentTime, $id);
            }
            $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('market_price');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZADD($cacheKey, $cartoonRole['market_price'] + $payAmount, $id);
            }
            if (!$isOldFans)
            {
                $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('fans_count');
                Redis::ZADD($cacheKey, $cartoonRole['fans_count'] + 1, $id);
            }
        }
        else if (!$doIPO)
        {
            // 融资中的公司
            $cacheKey = $cartoonRoleRepository->newbieIdolListCacheKey('star_count');
            Redis::ZADD($cacheKey, $cartoonRole['star_count'] + $buyCount, $id);
        }
        // 更新最近的入股列表
        $cacheKey = $cartoonRoleRepository->recentBuyStockCacheKey();
        Redis::ZADD($cacheKey, $currentTime, "{$userId}-{$id}-{$buyCount}-{$payAmount}");
        // 如果是新股民，就让总数加1
        if (!$isOldFans)
        {
            $cacheKey = $cartoonRoleRepository->stockBuyerTotolCountKey();
            if (Redis::EXISTS($cacheKey))
            {
                Redis::INCR($cacheKey);
            }
        }
        // 股市总盘增加
        $cacheKey = $cartoonRoleRepository->stockBuyerTotalMoneyKey();
        if (Redis::EXISTS($cacheKey))
        {
            Redis::INCRBYFLOAT($cacheKey, $payAmount);
        }

        return $this->resOK();
    }

    // 偶像的市值变动图标数据
    public function stochChart($id)
    {
        $cartoonRepository = new CartoonRoleRepository();
        $data = $cartoonRepository->idol24HourStockChartData($id);

        return $this->resOK($data);
    }

    // 股东列表
    public function owners(Request $request, $id)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $cartoonRole = $cartoonRoleRepository->item($id);
        if (is_null($cartoonRole))
        {
            return $this->resErrNotFound();
        }

        $sort = $request->get('sort') ?: 'biggest';
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $minId = $request->get('minId') ?: 0;

        $idsObj = $sort === 'newest'
            ? $cartoonRoleRepository->newOwnerIds($id, $minId)
            : $cartoonRoleRepository->bigOwnerIds($id, $seen);

        $ids = $idsObj['ids'];
        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'total' => 0,
                'noMore' => true
            ]);
        }

        $userRepository = new UserRepository();
        $users = [];
        foreach ($ids as $userId => $score)
        {
            $user = $userRepository->item($userId);
            if (is_null($user))
            {
                continue;
            }
            $user['score'] = $score;
            $users[] = $user;
        }

        $transformer = new CartoonRoleTransformer();
        if ($sort === 'newest')
        {
            $list = $transformer->new_owners($users);
        }
        else
        {
            $list = $transformer->big_owners($users);
        }

        return $this->resOK([
            'list' => $list,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

    // 最近发生的交易
    public function recentDealList(Request $request)
    {
        $page = $request->get('page') ?: 0;
        $cartoonRoleRepository = new CartoonRoleRepository();
        $list = $cartoonRoleRepository->RedisSort($cartoonRoleRepository->recentDealStockCacheKey(), function ()
        {
            return [];
        }, true, true);

        $idsObj = $cartoonRoleRepository->filterIdsByPage($list, $page, 10, true);
        $list = $idsObj['ids'];
        if (empty($list))
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true,
                'total' => 0
            ]);
        }
        $result = [];
        $userRepository = new UserRepository();
        foreach ($list as $item => $time)
        {
            // "{$idolId}-{$fromUserId}-{$toUserId}-{$buy_count}-{$pay_price}"
            $arr = explode('-', $item);
            if (count($arr) != 5)
            {
                continue;
            }
            $idol = $cartoonRoleRepository->item($arr[0]);
            if (is_null($idol))
            {
                continue;
            }
            $buyer = $userRepository->item($arr[1]);
            if (is_null($buyer))
            {
                continue;
            }
            $dealer = $userRepository->item($arr[2]);
            if (is_null($dealer))
            {
                continue;
            }
            $result[] = [
                'time' => $time,
                'idol' => [
                    'id' => $idol['id'],
                    'name' => $idol['name']
                ],
                'buyer' => [
                    'nickname' => $buyer['nickname'],
                    'zone' => $buyer['zone'],
                    'avatar' => $buyer['avatar']
                ],
                'dealer' => [
                    'nickname' => $dealer['nickname'],
                    'zone' => $dealer['zone']
                ],
                'count' => $arr[3],
                'price' => $arr[4]
            ];
        }

        return $this->resOK([
            'list' => $result,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

    // 最近的入股记录
    public function recentBuyList(Request $request)
    {
        $page = $request->get('page') ?: 0;
        $cartoonRoleRepository = new CartoonRoleRepository();
        $list = $cartoonRoleRepository->RedisSort($cartoonRoleRepository->recentBuyStockCacheKey(), function ()
        {
            return [];
        }, true, true);

        $idsObj = $cartoonRoleRepository->filterIdsByPage($list, $page, 10, true);
        $list = $idsObj['ids'];
        if (empty($list))
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true,
                'total' => 0
            ]);
        }
        $result = [];
        $userRepository = new UserRepository();
        foreach ($list as $item => $time)
        {
            // "{$userId}-{$id}-{$buyCount}"
            $arr = explode('-', $item);
            if (count($arr) != 4)
            {
                continue;
            }
            $user = $userRepository->item($arr[0]);
            if (is_null($user))
            {
                continue;
            }
            $idol = $cartoonRoleRepository->item($arr[1]);
            if (is_null($idol))
            {
                continue;
            }
            $result[] = [
                'user' => [
                    'zone' => $user['zone'],
                    'nickname' => $user['nickname'],
                    'avatar' => $user['avatar']
                ],
                'idol' => [
                    'id' => $idol['id'],
                    'name' => $idol['name']
                ],
                'count' => $arr[2],
                'price' => $arr[3],
                'time' => $time
            ];
        }

        return $this->resOK([
            'list' => $result,
            'noMore' => $idsObj['noMore'],
            'total' => $idsObj['total']
        ]);
    }

    // 股市的信息
    public function stockMeta()
    {
        $cartoonRoleRepository = new CartoonRoleRepository();

        return $this->resOK([
            'buyer_count' => $cartoonRoleRepository->stockBuyerTotalCount(),
            'money_count' => $cartoonRoleRepository->stockBuyerTotalMoney(),
            'deal_count' =>$cartoonRoleRepository->stockDealTotalCount(),
            'exchang_money_count' => $cartoonRoleRepository->stockDealTotalMoney()
        ]);
    }

    // 我发起的交易
    public function myDeal()
    {
        $userId = $this->getAuthUserId();
        $ids = VirtualIdolDeal
            ::where('user_id', $userId)
            ->orderBy('id', 'DESC')
            ->pluck('id')
            ->toArray();

        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true,
                'total' => 0
            ]);
        }

        $cartoonRoleRepository = new CartoonRoleRepository();
        $result = [];
        foreach ($ids as $id)
        {
            $deal = $cartoonRoleRepository->dealItem($id, true);
            $idol = $cartoonRoleRepository->item($deal['idol_id']);
            if (is_null($idol))
            {
                continue;
            }
            $deal['idol'] = $idol;
            $result[] = $deal;
        }
        $cartoonRoleTransformer = new CartoonRoleTransformer();

        return $this->resOK([
            'list' => $cartoonRoleTransformer->mineDealList($result),
            'noMore' => true,
            'total' => count($result)
        ]);
    }

    /**
     * 24小时偶像动态榜单
     *
     * @Get("/cartoon_role/list/today")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "偶像列表"})
     * })
     */
    public function todayActivity()
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $ids = $cartoonRoleRepository->RedisSort('cartoon_role_today_activity_ids', function ()
        {
            $list = CartoonRoleFans
                ::select(DB::raw('count(*) as count, role_id'))
                ->orderBy('count', 'DESC')
                ->groupBy('role_id')
                ->take(100)
                ->pluck('role_id');

            $result = [];
            $total = count($list);
            foreach ($list as $i => $item)
            {
                $result[$item] = $total - $i;
            }

            return $result;
        });

        $list = $cartoonRoleRepository->list($ids);

        foreach ($list as $i => $item)
        {
            $list[$i] = [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'avatar' => $item['avatar'],
                'intro' => $item['intro']
            ];
        }
        $cartoonRoleStarCounter = new CartoonRoleStarCounter();
        $cartoonRoleFansCounter = new CartoonRoleFansCounter();
        $list = $cartoonRoleFansCounter->batchGet($list, 'fans_count');
        $list = $cartoonRoleStarCounter->batchGet($list, 'star_count');

        return $this->resOK($list);
    }

    /**
     * 贡献最多的10人
     *
     * @Get("/cartoon_role/list/dalao")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "偶像列表"})
     * })
     */
    public function dalaoUsers()
    {
        $userRepository = new UserRepository();
        $list = $userRepository->Cache('cartoon_role_star_dalao_user_ids', function ()
        {
            return LightCoinRecord
                ::where('to_product_type', 9)
                ->select(DB::raw('count(*) as count, from_user_id'))
                ->groupBy('from_user_id')
                ->orderBy('count', 'DESC')
                ->take(11)
                ->get()
                ->toArray();
        });

        $result = [];
        foreach ($list as $i => $item)
        {
            $user = $userRepository->item($item['from_user_id']);
            if (is_null($user))
            {
                continue;
            }

            $result[] = [
                'id' => $user['id'],
                'contribution' => (int)$item['count'],
                'nickname' => $user['nickname'],
                'avatar' => $user['avatar'],
                'zone' => $user['zone']
            ];
        }

        return $this->resOK($result);
    }

    /**
     * 今天最活跃的10个人
     *
     * @Get("/cartoon_role/list/newbie")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "偶像列表"})
     * })
     */
    public function newbieUsers()
    {
        $userRepository = new UserRepository();
        $list = $userRepository->Cache('cartoon_role_star_newbie_users', function ()
        {
            return LightCoinRecord
                ::where('to_product_type', 9)
                ->where('created_at', '>', Carbon::now()->addDays(-1))
                ->select(DB::raw('count(*) as count, from_user_id'))
                ->groupBy('from_user_id')
                ->orderBy('count', 'DESC')
                ->take(11)
                ->get()
                ->toArray();
        }, 'm');

        $result = [];
        foreach ($list as $i => $item)
        {
            $user = $userRepository->item($item['from_user_id']);
            if (is_null($user))
            {
                continue;
            }

            $result[] = [
                'id' => $user['id'],
                'contribution' => (int)$item['count'],
                'nickname' => $user['nickname'],
                'avatar' => $user['avatar'],
                'zone' => $user['zone']
            ];
        }

        return $this->resOK($result);
    }

    // 公开创建偶像
    public function publicCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumi_id' => 'required|integer',
            'name' => 'required|string|min:1|max:35',
            'alias' => 'required|string|min:1|max:120',
            'intro' => 'required|string|min:1|max:400',
            'avatar' => 'required|string'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $user = $this->getAuthUser();
        $virtualCoinService = new VirtualCoinService();
        $balance = $virtualCoinService->hasMoneyCount($user);
        $CREATE_PRICE = 10;
        if ($balance < $CREATE_PRICE)
        {
            return $this->resErrBad('至少需要10个虚拟币');
        }
        $userId = $user->id;
        $name = Purifier::clean($request->get('name'));
        $alias = Purifier::clean($request->get('alias'));
        $intro = Purifier::clean($request->get('intro'));
        $avatar = $request->get('avatar');
        $bangumiId = $request->get('bangumi_id');
        $hasRole = CartoonRole
            ::where('bangumi_id', $bangumiId)
            ->where('name', $name)
            ->count();

        if ($hasRole)
        {
            return $this->resErrBad('该偶像已存在，请勿重复创建');
        }

        $imageFilter = new ImageFilter();
        $badImage = $imageFilter->bad($avatar);
        if ($badImage)
        {
            return $this->resErrBad('请勿使用违规图片');
        }

        $cartoonRoleRepository = new CartoonRoleRepository();

        $role = CartoonRole::create([
            'name' => $name,
            'bangumi_id' => $bangumiId,
            'alias' => $alias,
            'intro' => $intro,
            'avatar' => $cartoonRoleRepository->convertImagePath($avatar),
            'state' => $userId,
            'market_price' => $CREATE_PRICE
        ]);

        if (is_null($role))
        {
            return $this->resErrServiceUnavailable('偶像创建失败');
        }

        $roleId = $role->id;
        $result = $virtualCoinService->cheerForIdol($userId, $roleId, $CREATE_PRICE);
        if ($result)
        {
            CartoonRole
                ::where('id', $roleId)
                ->update([
                    'star_count' => $CREATE_PRICE,
                    'fans_count' => 1
                ]);

            VirtualIdolOwner::create([
                'idol_id' => $roleId,
                'user_id' => $userId,
                'stock_count' => $CREATE_PRICE,
                'total_price' => $CREATE_PRICE
            ]);
        }

        $cartoonRoleRepository->migrateSearchIndex('C', $roleId);
        $cartoonRoleTrendingService = new CartoonRoleTrendingService($bangumiId);
        $cartoonRoleTrendingService->create($roleId);

        $bangumiActivityService = new BangumiActivity();
        $bangumiActivityService->update($bangumiId, 3);
        $userActivityService = new UserActivity();
        $userActivityService->update($userId, 3);

        return $this->resCreated($roleId);
    }

    // 创建一笔交易
    public function createDeal(Request $request)
    {
        $userId = $this->getAuthUserId();
        $idolId = $request->get('idol_id');
        $dealId = $request->get('id');
        $product_price = floatval($request->get('product_price'));
        $product_count = floatval($request->get('product_count'));
        $cartoonRoleRepository = new CartoonRoleRepository();
        $idol = $cartoonRoleRepository->item($idolId);
        if (is_null($idol))
        {
            return $this->resErrNotFound('不存在的偶像');
        }

        if ($this->calculate($product_count * $product_price) < 0.01)
        {
            return $this->resErrBad('交易额不能低于0.01');
        }

        $deal = null;
        if ($dealId)
        {
            $deal = VirtualIdolDeal
                ::where('user_id', $userId)
                ->where('idol_id', $idolId)
                ->first();

            if (is_null($deal))
            {
                return $this->resErrNotFound('未找到当前交易');
            }

            $lastEditAt = $deal['last_edit_at'];
            if ($lastEditAt && strtotime($lastEditAt) > strtotime('10 minute ago'))
            {
                return $this->resErrBad('每10分钟只能修改一次');
            }
        }
        $myStock = VirtualIdolOwner
            ::where('user_id', $userId)
            ->where('idol_id', $idolId)
            ->pluck('stock_count')
            ->first();

        if (!$myStock)
        {
            return $this->resErrNotFound('你未持有该偶像的股份');
        }

        if (floatval($myStock) < $product_count)
        {
            return $this->resErrBad('没有足够的股份发起交易');
        }

        if ($deal)
        {
            VirtualIdolDeal
                ::where('id', $deal->id)
                ->update([
                    'product_price' => $product_price,
                    'product_count' => $product_count,
                    'last_count' => $product_count,
                    'last_edit_at' => Carbon::now()
                ]);
        }
        else
        {
            $deal = VirtualIdolDeal::create([
                'user_id' => $userId,
                'idol_id' => $idolId,
                'product_price' => $product_price,
                'product_count' => $product_count,
                'last_count' => $product_count,
                'last_edit_at' => Carbon::now()
            ]);
        }
        $cacheKey = $cartoonRoleRepository->idolDealListCacheKey();
        if (Redis::EXISTS($cacheKey))
        {
            Redis::ZADD($cacheKey, strtotime('now'), $deal->id);
        }

        return $this->resCreated($deal->id);
    }

    // 删除交易
    public function deleteDeal(Request $request)
    {
        $dealId = $request->get('id');
        $cartoonRoleRepository = new CartoonRoleRepository();
        $deal = $cartoonRoleRepository->dealItem($dealId);
        $userId = $this->getAuthUserId();
        if ($deal['user_id'] != $userId)
        {
            return $this->resErrRole();
        }

        VirtualIdolDeal
            ::where('id', $dealId)
            ->delete();

        Redis::DEL($cartoonRoleRepository->idolDealItemCacheKey($dealId));
        $cacheKey = $cartoonRoleRepository->idolDealListCacheKey();
        if (Redis::EXISTS($cacheKey))
        {
            Redis::ZREM($cacheKey, $dealId);
        }

        return $this->resOK();
    }

    // 进行交易
    public function makeDeal(Request $request)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $dealId = $request->get('deal_id');
        $deal = $cartoonRoleRepository->dealItem($dealId);
        if (is_null($deal))
        {
            return $this->resErrNotFound('这笔交易已经取消了');
        }
        $user = $this->getAuthUser();
        if ($deal['user_id'] == $user->id)
        {
            return $this->resErrNotFound('不能与自己交易');
        }
        $idol = $cartoonRoleRepository->item($deal['idol_id']);
        if (is_null($idol))
        {
            return $this->resErrNotFound('这个偶像已经被删除了');
        }
        $buy_count = $request->get('buy_count');
        $pay_price = $request->get('pay_price');
        if ($buy_count > $deal['last_count'])
        {
            return $this->resErrBad("只剩下{$deal['last_count']}份了");
        }
        $shouldPayAmount = $this->calculate($buy_count * $deal['product_price']);
        if ($shouldPayAmount != $pay_price)
        {
            return $this->resErrBad("价格已经变成￥{$deal['product_price']}每股了");
        }
        $virtualCoinService = new VirtualCoinService();
        $pocket = $virtualCoinService->hasMoneyCount($user);
        if ($pocket < $pay_price)
        {
            return $this->resErrBad('没有足够的虚拟币了');
        }

        $fromUserId = $user->id;
        $toUserId = $deal['user_id'];
        $idolId = $deal['idol_id'];

        $oldOwnerData = VirtualIdolOwner
            ::where('idol_id', $idolId)
            ->where('user_id', $toUserId)
            ->first();

        if (is_null($oldOwnerData))
        {
            return $this->resErrBad('这笔交易已经结束了');
        }

        $rollback = false;
        $addOwnerCount = 0;
        $deleteOldOwner = false;
        $createNewOwner = false;

        DB::beginTransaction();
        try
        {
            // 虚拟币交换
            $result = $virtualCoinService->makeIdolDeal($fromUserId, $toUserId, $idolId, $pay_price);
            if (!$result)
            {
                $rollback = true;
            }
            // 先减去卖家
            if ($oldOwnerData->stock_count == $buy_count)
            {
                // 卖家清仓了
                $remove_price = $oldOwnerData->total_price;
                $addOwnerCount--;
                $deleteOldOwner = true;
                VirtualIdolOwner
                    ::where('id', $oldOwnerData->id)
                    ->delete();
            }
            else
            {
                // 减去他的持股数
                VirtualIdolOwner
                    ::where('id', $oldOwnerData->id)
                    ->increment('stock_count', -$buy_count);

                $remove_price = $this->calculate($buy_count / $oldOwnerData->stock_count * $oldOwnerData->total_price);
                // 减去他的持股价值
                VirtualIdolOwner
                    ::where('id', $oldOwnerData->id)
                    ->increment('total_price', -$remove_price);
            }
            // 再发给买家
            $newOwnerData = VirtualIdolOwner
                ::where('idol_id', $idolId)
                ->where('user_id', $fromUserId)
                ->first();

            if (is_null($newOwnerData))
            {
                // 是个新的股东
                $addOwnerCount++;
                $createNewOwner = true;
                $result = VirtualIdolOwner::create([
                    'user_id' => $fromUserId,
                    'idol_id' => $idolId,
                    'stock_count' => $buy_count,
                    'total_price' => $pay_price
                ]);
                if (!$result)
                {
                    $rollback = true;
                }
            }
            else
            {
                // 是个老股东
                VirtualIdolOwner
                    ::where('id', $newOwnerData->id)
                    ->increment('stock_count', $buy_count);

                VirtualIdolOwner
                    ::where('id', $newOwnerData->id)
                    ->increment('total_price', $pay_price);
            }

            // 修改偶像数据
            if ($addOwnerCount)
            {
                CartoonRole
                    ::where('id', $idolId)
                    ->increment('fans_count', $addOwnerCount);
            }

            $deltaPrice = $pay_price - $remove_price;
            if ($deltaPrice)
            {
                CartoonRole
                    ::where('id', $idolId)
                    ->increment('market_price', $deltaPrice);
            }

            // 写一条交易记录
            $result = VirtualIdolDealRecord
                ::create([
                    'from_user_id' => $fromUserId,
                    'buyer_id' => $toUserId,
                    'idol_id' => $idolId,
                    'deal_id' => $dealId,
                    'exchange_amount' => $pay_price,
                    'exchange_count' => $buy_count
                ]);

            if (!$result)
            {
                $rollback = true;
            }

            $deleteDeal = false;
            if ($deal['last_count'] == $buy_count)
            {
                $deleteDeal = true;
            }
            // 修改交易的 last_count
            VirtualIdolDeal
                ::where('id', $dealId)
                ->increment('last_count', -$buy_count);
            if ($deleteDeal)
            {
                VirtualIdolDeal
                    ::where('id', $dealId)
                    ->delete();
            }
            // TODO：操作缓存，是否应该使用 pipeline 优化性能？
            // 交易的数据
            $currentTime = strtotime('now');
            $cacheKey = $cartoonRoleRepository->idolDealItemCacheKey($dealId);
            if (Redis::EXISTS($cacheKey))
            {
                if ($deleteDeal)
                {
                    Redis::DEL($cacheKey);
                }
                else
                {
                    Redis::HINCRBYFLOAT($cacheKey, 'last_count', -$buy_count);
                }
            }
            // 偶像的数据
            $cacheKey = $cartoonRoleRepository->idolItemCacheKey($idolId);
            if (Redis::EXISTS($cacheKey))
            {
                if ($addOwnerCount)
                {
                    Redis::HINCRBYFLOAT($cacheKey, 'fans_count', $addOwnerCount);
                }
                if ($deltaPrice)
                {
                    Redis::HINCRBYFLOAT($cacheKey, 'market_price', $deltaPrice);
                }
            }
            // 最新股东列表
            $cacheKey = $cartoonRoleRepository->newOwnerIdsCacheKey($idolId);
            Redis::ZADD($cacheKey, $currentTime, $fromUserId);
            if (Redis::EXISTS($cacheKey) && $deleteOldOwner)
            {
                Redis::ZREM($cacheKey, $toUserId);
            }
            // 大股东列表
            $cacheKey = $cartoonRoleRepository->bigOwnerIdsCacheKey($idolId);
            if (Redis::EXISTS($cacheKey))
            {
                if ($createNewOwner)
                {
                    Redis::ZADD($cacheKey, $buy_count, $fromUserId);
                }
                else
                {
                    Redis::ZADD($cacheKey, $newOwnerData->stock_count + $buy_count, $fromUserId);
                }
                if ($deleteOldOwner)
                {
                    Redis::ZREM($cacheKey, $toUserId);
                }
                else
                {
                    Redis::ZADD($cacheKey, $oldOwnerData->stock_count - $buy_count, $toUserId);
                }
            }
            // 修改交易大厅列表
            $cacheKey = $cartoonRoleRepository->idolDealListCacheKey();
            if (Redis::EXISTS($cacheKey))
            {
                if ($deleteDeal)
                {
                    Redis::ZREM($cacheKey, $dealId);
                }
                else
                {
                    Redis::ZADD($cacheKey, $currentTime, $dealId);
                }
            }
            // 修改上市公司列表数据
            if ($deltaPrice)
            {
                // 市值列表
                $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('market_price');
                if (Redis::EXISTS($cacheKey))
                {
                    Redis::ZADD($cacheKey, $idol['market_price'] + $deltaPrice, $idolId);
                }
                // 市值总盘数额变动
                $cacheKey = $cartoonRoleRepository->stockBuyerTotalMoneyKey();
                if (Redis::EXISTS($cacheKey))
                {
                    Redis::INCRBYFLOAT($cacheKey, $deltaPrice);
                }
            }
            if ($addOwnerCount)
            {
                // 投资人列表
                $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('fans_count');
                if (Redis::EXISTS($cacheKey))
                {
                    Redis::ZADD($cacheKey, $idol['fans_count'] + $addOwnerCount, $idolId);
                }
                // 总投资人统计变动
                $cacheKey = $cartoonRoleRepository->stockBuyerTotolCountKey();
                if (Redis::EXISTS($cacheKey))
                {
                    Redis::INCRBY($cacheKey, $addOwnerCount);
                }
            }
            // 动态列表
            $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('activity');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZADD($cacheKey, $currentTime, $idolId);
            }
            // 修改用户列表数据（买家）
            $cacheKey = $cartoonRoleRepository->userIdolListCacheKey($fromUserId);
            if (Redis::EXISTS($cacheKey))
            {
                if ($createNewOwner)
                {
                    Redis::ZADD($cacheKey, $buy_count, $idolId);
                }
                else
                {
                    Redis::ZADD($cacheKey, $newOwnerData->stock_count + $buy_count, $idolId);
                }
            }
            // 修改用户列表数据（卖家）
            $cacheKey = $cartoonRoleRepository->userIdolListCacheKey($toUserId);
            if (Redis::EXISTS($cacheKey))
            {
                if ($deleteOldOwner)
                {
                    Redis::ZREM($cacheKey, $idolId);
                }
                else
                {
                    Redis::ZADD($cacheKey, $oldOwnerData->stock_count - $buy_count, $idolId);
                }
            }
            // 最近的交易列表增加
            $cacheKey = $cartoonRoleRepository->recentDealStockCacheKey();
            Redis::ZADD($cacheKey, $currentTime, "{$idolId}-{$fromUserId}-{$toUserId}-{$buy_count}-{$pay_price}");
            // 交易次数增加
            $cacheKey = $cartoonRoleRepository->stockDealTotalCountCacheKey();
            if (Redis::EXISTS($cacheKey))
            {
                Redis::INCR($cacheKey);
            }
            // 交易金额增加
            $cacheKey = $cartoonRoleRepository->stockDealTotalMoneyCacheKey();
            if (Redis::EXISTS($cacheKey))
            {
                Redis::INCRBYFLOAT($cacheKey, $pay_price);
            }
        }
        catch (\Exception $e)
        {
            Log::error($e);
            $rollback = true;
        }
        if ($rollback)
        {
            DB::rollBack();
            return $this->resErrServiceUnavailable('交易失败，请稍候再试');
        }

        DB::commit();
        return $this->resOK('交易成功');
    }

    // 获取用户可交易的股份
    public function getCurrentDeal($id)
    {
        $userId = $this->getAuthUserId();
        $deal = VirtualIdolDeal
            ::where('user_id', $userId)
            ->where('idol_id', $id)
            ->first();

        return $this->resOK($deal);
    }

    // 获取我发起的交易列表
    public function getMyIdolDeal($id)
    {
        $userId = $this->getAuthUserId();
        $deal = VirtualIdolDeal
            ::where('user_id', $userId)
            ->where('idol_id', $id)
            ->first();

        $has_star = VirtualIdolOwner
            ::where('idol_id', $id)
            ->pluck('stock_count')
            ->first();

        return $this->resOK([
            'deal' => $deal,
            'has_star' => $has_star
        ]);
    }

    // 新建一个股份发行的草案
    public function createIdolMarketPriceDraft(Request $request)
    {
        $userId = $this->getAuthUserId();
        $idolId = $request->get('idol_id');
        $stock_price = $request->get('stock_price');
        $add_stock_count = $request->get('add_stock_count');

        $cartoonRoleRepository = new CartoonRoleRepository();
        $idol = $cartoonRoleRepository->item($idolId);
        if (is_null($idol))
        {
            return $this->resErrNotFound();
        }

        if ($idol['boss_id'] != $userId)
        {
            return $this->resErrRole();
        }

        /*
        if (
            floatval($idol['max_stock_count']) != 0 &&
            floatval($idol['max_stock_count']) != floatval($idol['star_count'])
        )
        {
            return $this->resErrBad('挂牌交易中不能发起提案');
        }
        */

        $hasDraft = VirtualIdolPriceDraft
            ::where('idol_id', $idolId)
            ->where('result', 0)
            ->count();

        if ($hasDraft)
        {
            return $this->resErrBad('有正在商议的提案');
        }

        $draft = VirtualIdolPriceDraft
            ::create([
                'user_id' => $userId,
                'idol_id' => $idolId,
                'stock_price' => $stock_price,
                'add_stock_count' => $add_stock_count
            ]);

        $cacheKey = $cartoonRoleRepository->lastIdolMarketPriceDraftCacheKey($idolId);
        Redis::SET($cacheKey, $draft->id);

        return $this->resCreated($draft);
    }

    // 修改大股东寄语和QQ群号
    public function changeIdolProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qq_group' => 'present|integer',
            'lover_words' => 'required|string|min:1|max:20'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $userId = $this->getAuthUserId();
        $idolId = $request->get('idol_id');
        $cartoonRoleRepository = new CartoonRoleRepository();
        $idol = $cartoonRoleRepository->item($idolId);
        if (is_null($idol))
        {
            return $this->resErrNotFound();
        }

        if ($idol['boss_id'] != $userId)
        {
            return $this->resErrRole();
        }

        $lastEditAt = $idol['last_edit_at'];
        if ($lastEditAt && strtotime($lastEditAt) > strtotime('1 week ago'))
        {
            return $this->resErrBad('每周只能修改一次');
        }

        $lover_words = Purifier::clean($request->get('lover_words'));
        $qq_group = $request->get('qq_group');
        if ((isset($idol['qq_group']) && $idol['qq_group']) || !$qq_group)
        {
            $qq_group = isset($idol['qq_group']) ? $idol['qq_group'] : '';
        }

        $now = Carbon::now();
        CartoonRole
            ::where('id', $idolId)
            ->update([
                'qq_group' => $qq_group,
                'lover_words' => $lover_words,
                'last_edit_at' => $now
            ]);

        $cacheKey = $cartoonRoleRepository->idolItemCacheKey($idolId);
        if (Redis::EXISTS($cacheKey))
        {
            Redis::HSET($cacheKey, 'qq_group', $qq_group);
            Redis::HSET($cacheKey, 'lover_words', $lover_words);
            Redis::HSET($cacheKey, 'last_edit_at', $now);
        }

        return $this->resNoContent();
    }

    // 获取偶像的股份发行草案列表
    public function getIdolMarketPriceDraftList(Request $request)
    {
        $userId = $this->getAuthUserId();
        $idolId = $request->get('idol_id');
        $cartoonRoleRepository = new CartoonRoleRepository();
        $idol = $cartoonRoleRepository->item($idolId);
        if (is_null($idol))
        {
            return $this->resErrNotFound();
        }

        $list = VirtualIdolPriceDraft
            ::where('idol_id', $idolId)
            ->orderBy('id', 'DESC')
            ->get()
            ->toArray();

        if (empty($list))
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true,
                'total' => 0
            ]);
        }

        $userRepository = new UserRepository();
        $result = [];
        foreach ($list as $draft)
        {
            $user = $userRepository->item($draft['user_id']);
            if (is_null($user))
            {
                continue;
            }

            $result[] = [
                'user' => [
                    'id' => $user['id'],
                    'nickname' => $user['nickname'],
                    'avatar' => $user['avatar'],
                    'zone' => $user['zone']
                ],
                'id' => $draft['id'],
                'stock_price' => $draft['stock_price'],
                'add_stock_count' => $draft['add_stock_count'],
                'result' => intval($draft['result']),
                'created_at' => $draft['created_at'],
                'idol_id' => $draft['idol_id'],
                'pass_percent' => 0,
                'ban_percent' => 0,
                'pass_count' => 0,
                'ban_count' => 0,
                'voted' => 0
            ];
        }
        if ($result[0]['result'] == 0)
        {
            $draft = $result[0];
            $idolVoteService = new IdolVoteService();
            $agreeUsersId = $idolVoteService->agreeUsersId($draft['id']);
            if (count($agreeUsersId))
            {
                $amount = VirtualIdolOwner
                    ::where('idol_id', $idolId)
                    ->whereIn('user_id', $agreeUsersId)
                    ->sum('stock_count');

                $result[0]['pass_percent'] = sprintf("%.2f", $amount / $idol['star_count'] * 100);
                $result[0]['pass_count'] = count($agreeUsersId);
            }
            $bannedUsersId = $idolVoteService->bannedUsersId($draft['id']);
            if (count($bannedUsersId))
            {
                $amount = VirtualIdolOwner
                    ::where('idol_id', $idolId)
                    ->whereIn('user_id', $bannedUsersId)
                    ->sum('stock_count');

                $result[0]['ban_percent'] = sprintf("%.2f", $amount / $idol['star_count'] * 100);
                $result[0]['ban_count'] = count($bannedUsersId);
            }

            $result[0]['voted'] = $idolVoteService->check($userId, $draft['id']);
        }

        return $this->resOK([
            'list' => $result,
            'noMore' => true,
            'total' => count($result)
        ]);
    }

    // 为股份发行草案投票
    public function voteIdolMarketPriceDraft(Request $request)
    {
        $userId = $this->getAuthUserId();
        $idolId = $request->get('idol_id');
        $draftId = $request->get('draft_id');
        $isAgree = $request->get('is_agree');

        $draft = VirtualIdolPriceDraft
            ::where('id', $draftId)
            ->where('result', 0)
            ->where('idol_id', $idolId)
            ->count();
        if (!$draft)
        {
            return $this->resErrBad();
        }

        $isOwner = VirtualIdolOwner
            ::where('idol_id', $idolId)
            ->where('user_id', $userId)
            ->count();
        if (!$isOwner)
        {
            return $this->resErrRole('你不是股东，无投票权');
        }

        $idolVoteService = new IdolVoteService();
        if ($isAgree)
        {
            $resultScore = $idolVoteService->toggleLike($userId, $draftId);
        }
        else
        {
            $resultScore = $idolVoteService->toggleDislike($userId, $draftId);
        }
        /**
         * $resultScore
         *  0 => 弃权
         *  1 => 同意
         * -1 => 反对
         */
        return $this->resCreated($resultScore);
    }

    // 删除某个股份发行草案
    public function deleteIdolMarketPriceDraft(Request $request)
    {
        $userId = $this->getAuthUserId();
        $draftId = $request->get('draft_id');
        $draft = VirtualIdolPriceDraft
            ::where('id', $draftId)
            ->first();

        if (is_null($draft))
        {
            return $this->resErrNotFound();
        }

        if ($draft['user_id'] != $userId)
        {
            return $this->resErrRole();
        }

        if ($draft['result'] != 0)
        {
            return $this->resErrBad('不能删除已定论的草案');
        }

        $cartoonRoleRepository = new CartoonRoleRepository();
        $idol = $cartoonRoleRepository->item($draft['idol_id']);
        if (is_null($idol))
        {
            return $this->resErrNotFound();
        }

        if ($idol['boss_id'] != $userId)
        {
            return $this->resErrRole();
        }

        VirtualIdolPriceDraft
            ::where('id', $draftId)
            ->delete();

        return $this->resNoContent();
    }

    // 交易大厅列表
    public function getDealList(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = $request->get('take') ?: 20;
        $cartoonRoleRepository = new CartoonRoleRepository();
        $idsObj = $cartoonRoleRepository->idolDealIds($seen, $take);

        if (empty($idsObj['ids']))
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true,
                'total' => 0
            ]);
        }

        $userRepository = new UserRepository();
        $list = [];
        foreach ($idsObj['ids'] as $dealId)
        {
            $deal = $cartoonRoleRepository->dealItem($dealId);
            if (is_null($deal))
            {
                continue;
            }
            $user = $userRepository->item($deal['user_id']);
            if (is_null($user))
            {
                continue;
            }
            $idol = $cartoonRoleRepository->item($deal['idol_id']);
            if (is_null($idol))
            {
                continue;
            }
            $deal['user'] = $user;
            $deal['idol'] = $idol;
            $list[] = $deal;
        }

        $cartoonRoleTransformer = new CartoonRoleTransformer();

        return $this->resOK([
            'list' => $cartoonRoleTransformer->dealList($list),
            'noMore' => $idsObj['noMore'],
            'total' => $idsObj['total']
        ]);
    }

    // 获取偶像列表
    public function getIdolList(Request $request)
    {
        $type = $request->get('type') ?: 'trending'; // trending 或者 user 或者 bangumi
        $state = $request->get('state') ?: 0;   // 0 是融资中，1是已上市
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = $request->get('take') ?: 20;
        $sort = $request->get('sort') ?: 'newest';
        $id = $request->get('id');
        $cartoonRoleRepository = new CartoonRoleRepository();
        if ($type === 'user')
        {
            $idsObj = $cartoonRoleRepository->userIdolList($id, $seen, $take);
        }
        else if ($type === 'bangumi')
        {
            $idsObj = $cartoonRoleRepository->bangumiIdolList($id, $seen, $take);
        }
        else
        {
            if ($state == 0)
            {
                // 融资中的公司
                $idsObj = $cartoonRoleRepository->newbieIdolList($sort, $seen, $take);
            }
            else
            {
                $idsObj = $cartoonRoleRepository->marketIdolList($sort, $seen, $take);
            }
        }
        $ids = [];
        foreach ($idsObj['ids'] as $id => $score)
        {
            $ids[] = $id;
        }

        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'noMore' => true,
                'total' => 0
            ]);
        }

        $userRepository = new UserRepository();
        $list = $cartoonRoleRepository->list($ids);
        foreach ($list as $i => $item)
        {
            $list[$i]['market_trend'] = $cartoonRoleRepository->idol24HourStockChartData($item['id']);
            $boss = null;
            if ($item['boss_id'])
            {
                $boss = $userRepository->item($item['boss_id']);
            }
            $list[$i]['boss'] = is_null($boss) ? null : [
                'zone' => $boss['zone'],
                'avatar' => $boss['avatar'],
                'nickname' => $boss['nickname']
            ];
        }
        $cartoonRoleTransformer = new CartoonRoleTransformer();

        return $this->resOK([
            'list' => $cartoonRoleTransformer->market($list),
            'noMore' => count($list) ? $idsObj['noMore'] : true,
            'total' => $idsObj['total']
        ]);
    }

    /**
     * 创建偶像
     *
     * @Post("/cartoon_role/manager/create")
     *
     * @Parameters({
     *      @Parameter("bangumi_id", description="所选的番剧 id", type="integer", required=true),
     *      @Parameter("name", description="偶像名称", type="string", required=true),
     *      @Parameter("alias", description="偶像别名，逗号分隔的昵称，选填", type="string", required=false),
     *      @Parameter("intro", description="偶像简介，200字以内纯文本", type="string", required=true),
     *      @Parameter("avatar", description="偶像头像，七牛传图返回的url", type="string", required=true),
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "偶像id"}),
     *      @Response(400, body={"code": 40003, "message": "偶像已存在"}),
     *      @Response(403, body={"code": 40301, "message": "没有权限"})
     * })
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumi_id' => 'required|integer',
            'name' => 'required|string|min:1|max:35',
            'alias' => 'required|string|min:1|max:120',
            'intro' => 'required|string|min:1|max:400',
            'avatar' => 'required|string'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $user = $this->getAuthUser();
        $userId = $user->id;
        $bangumiId = $request->get('bangumi_id');

        if (!$user->is_admin)
        {
            $bangumiManager = new BangumiManager();
            if (!$bangumiManager->isOwner($bangumiId, $userId))
            {
                return $this->resErrRole();
            }
        }

        $name = $request->get('name');
        $alias = $request->get('alias') ? $request->get('alias') : $name;
        $time = Carbon::now();

        $count = CartoonRole::whereRaw('bangumi_id = ? and name = ?', [$bangumiId, $name])
            ->count();

        if ($count)
        {
            return $this->resErrBad('该偶像可能已存在');
        }

        $cartoonRoleRepository = new CartoonRoleRepository();

        $id =  CartoonRole::insertGetId([
            'bangumi_id' => $bangumiId,
            'avatar' => $cartoonRoleRepository->convertImagePath($request->get('avatar')),
            'name' => $name,
            'intro' => Purifier::clean($request->get('intro')),
            'alias' => $alias,
            'state' => $userId,
            'created_at' => $time,
            'updated_at' => $time
        ]);

        $cartoonRoleRepository->migrateSearchIndex('C', $id);
        $cartoonRoleTrendingService = new CartoonRoleTrendingService($bangumiId);
        $cartoonRoleTrendingService->create($id);

        $bangumiActivityService = new BangumiActivity();
        $bangumiActivityService->update($bangumiId, 3);
        $userActivityService = new UserActivity();
        $userActivityService->update($userId, 3);

        return $this->resCreated($id);
    }

    /**
     * 编辑偶像
     *
     * @Post("/cartoon_role/manager/edit")
     *
     * @Parameters({
     *      @Parameter("id", description="偶像的id", type="integer", required=true),
     *      @Parameter("name", description="偶像名称", type="string", required=true),
     *      @Parameter("alias", description="偶像别名，逗号分隔的昵称，选填", type="string", required=false),
     *      @Parameter("intro", description="偶像简介，200字以内纯文本", type="string", required=true),
     *      @Parameter("avatar", description="偶像头像，七牛传图返回的url", type="string", required=true),
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(403, body={"code": 40301, "message": "没有权限"}),
     *      @Response(404, body={"code": 40401, "message": "偶像不存在"})
     * })
     */
    public function edit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumi_id' => 'required|integer',
            'name' => 'required|string|min:1|max:35',
            'alias' => 'required|string|min:1|max:120',
            'intro' => 'required|string|min:1|max:400',
            'avatar' => 'required|string'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $user = $this->getAuthUser();
        $userId = $user->id;
        $id = $request->get('id');

        $cartoonRoleRepository = new CartoonRoleRepository();
        $cartoonRole = $cartoonRoleRepository->item($id);
        if (is_null($cartoonRole))
        {
            return $this->resErrNotFound();
        }

        $bangumiId = $cartoonRole['bangumi_id'];

        if (!$user->is_admin)
        {
            $bangumiManager = new BangumiManager();
            if (!$bangumiManager->isOwner($bangumiId, $userId))
            {
                return $this->resErrRole();
            }
        }

        $alias = Purifier::clean($request->get('alias'));
        $alias = $alias ? $alias : $cartoonRole['alias'];

        CartoonRole
            ::where('id', $id)
            ->update([
                'bangumi_id' => $bangumiId,
                'avatar' => $request->get('avatar'),
                'name' => Purifier::clean($request->get('name')),
                'intro' => Purifier::clean($request->get('intro')),
                'alias' => $alias,
                'state' => $userId
            ]);

        $cartoonRoleRepository->migrateSearchIndex('U', $id);

        Redis::DEL($cartoonRoleRepository->idolItemCacheKey($id));

        return $this->resOK();
    }

    // 后台展示偶像详情
    public function adminShow(Request $request)
    {
        $result = CartoonRole::find($request->get('id'));

        return $this->resOK($result);
    }

    // 后台偶像列表
    public function trials()
    {
        $roles = CartoonRole::where('state', '<>', 0)
            ->select('id', 'state', 'name', 'bangumi_id')
            ->get();

        return $this->resOK($roles);
    }

    // 后台删除偶像
    // TODO：
    // 1. 删除该偶像的应援记录
    // 2. 删除该偶像的交易
    // 3. 归还偶像的团子给用户
    public function ban(Request $request)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $id = $request->get('id');
        $idol = $cartoonRoleRepository->item($id);
        if (is_null($idol))
        {
            return $this->resErrNotFound();
        }

        $bangumiId = $request->get('bangumi_id');

        DB::table('cartoon_role')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => Carbon::now()
            ]);

        $cartoonRoleTrendingService = new CartoonRoleTrendingService($bangumiId);
        $cartoonRoleTrendingService->delete($id);

        // 反还团子，但是溢价就惩罚掉了
        $data = VirtualIdolOwner
            ::where('idol_id', $id)
            ->select('user_id', 'stock_count')
            ->get()
            ->toArray();
        $virtualCoinService = new VirtualCoinService();
        foreach ($data as $item)
        {
            $virtualCoinService->coinGift($item['user_id'], $item['stock_count']);
        }

        // 删除该偶像的交易
        VirtualIdolDeal
            ::where('idol_id', $id)
            ->delete();

        $cacheKey = $cartoonRoleRepository->idolDealListCacheKey();
        Redis::DEL($cacheKey);

        // 删除该偶像的应援记录
        VirtualIdolOwner
            ::where('idol_id', $id)
            ->delete();

        $job = (new \App\Jobs\Search\Index('D', 'role', $id));
        dispatch($job);

        Redis::DEL($cartoonRoleRepository->idolItemCacheKey($id));

        return $this->resNoContent();
    }

    // 后台通过偶像
    public function pass(Request $request)
    {
        CartoonRole::where('id', $request->get('id'))
            ->update([
                'state' => 0
            ]);

        return $this->resNoContent();
    }

    // 后台根据用户 IP 来移除应援
    public function removeStarByIp(Request $request)
    {
        $userId = $this->getAuthUserId();
        if ($userId !== 1)
        {
            return $this->resErrRole();
        }

        $ip = $request->get('ip');
        $userIpAddress = new UserIpAddress();

        $userIds = $userIpAddress->addressUsers($ip);
        if (!$userIds)
        {
            return $this->resNoContent();
        }

        $cartoonRoleIds = CartoonRoleFans
            ::whereIn('role_id', $userIds)
            ->pluck('role_id');
        $virtualCoinService = new VirtualCoinService();

        foreach ($userIds as $userId)
        {
            CartoonRoleFans
                ::where('user_id', $userId)
                ->delete();

            $virtualCoinService->undoUserCheer($userId);
        }

        $cartoonRoleFansCounter = new CartoonRoleFansCounter();
        $cartoonRoleStarCounter = new CartoonRoleStarCounter();
        foreach ($cartoonRoleIds as $roleId)
        {
            $cartoonRoleFansCounter->deleteCache($roleId);
            $cartoonRoleStarCounter->deleteCache($roleId);
        }
        Redis::DEL('cartoon_role_star_dalao_user_ids');
        Redis::DEL('cartoon_role_star_newbie_users');

        return $this->resNoContent();
    }

    // 四舍六入算法
    protected function calculate($num, $precision = 2)
    {
        $pow = pow(10, $precision);
        if (
            (floor($num * $pow * 10) % 5 == 0) &&
            (floor($num * $pow * 10) == $num * $pow * 10) &&
            (floor($num * $pow) % 2 == 0)
        )
        {
            return floor($num * $pow) / $pow;
        } else {
            return round($num, $precision);
        }
    }
}
