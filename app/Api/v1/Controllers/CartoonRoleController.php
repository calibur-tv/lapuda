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
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use App\Models\LightCoinRecord;
use App\Models\VirtualIdolDeal;
use App\Models\VirtualIdolDealRecord;
use App\Models\VirtualIdolOwner;
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

            $starCount = CartoonRole
                ::where('id', $id)
                ->pluck('star_count')
                ->first();

            $cartoonRoleRepository->setIdolBiggestBoss($id);
            $cartoonRoleRepository->setIdolMaxStockCount($id, $starCount);
        }

        // 更新新股东列表
        $cacheKey = $cartoonRoleRepository->newOwnerIdsCacheKey($id);
        if (Redis::EXISTS($cacheKey))
        {
            Redis::ZADD($cacheKey, strtotime('now'), $userId);
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
                if ($isOldFans)
                {
                    Redis::ZADD($cacheKey, $oldOwnerData->stock_count + $buyCount, $userId);
                }
                else
                {
                    Redis::ZADD($cacheKey, $buyCount, $userId);
                }
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
                Redis::ZADD($cacheKey, strtotime('now'), $id);
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

        return $this->resOK();
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
        $list = $transformer->owners($users);

        return $this->resOK([
            'list' => $list,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
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

    // 开放创建偶像
    public function publicCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumi_id' => 'required|integer',
            'name' => 'required|min:1|max:35',
            'alias' => 'required|min:1|max:120',
            'intro' => 'required|min:1|max:400',
            'avatar' => 'required'
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
            Redis::ZADD($cacheKey, strtotime('now'), $fromUserId);
            if (Redis::EXISTS($cacheKey) && $deleteOldOwner)
            {
                Redis::ZREM($cacheKey, $toUserId);
            }
            // 大股东列表
            $cacheKey = $cartoonRoleRepository->bigOwnerIdsCacheKey($idolId);
            Redis::ZINCRBY($cacheKey, $buy_count, $fromUserId);
            if (Redis::EXISTS($cacheKey))
            {
                if ($deleteOldOwner)
                {
                    Redis::ZREM($cacheKey, $toUserId);
                }
                else
                {
                    Redis::ZINCRBY($cacheKey, -$buy_count, $toUserId);
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
                    Redis::ZADD($cacheKey, strtotime('now'), $dealId);
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
            }
            if ($addOwnerCount)
            {
                // 投资人列表
                $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('fans_count');
                if (Redis::EXISTS($cacheKey))
                {
                    Redis::ZADD($cacheKey, $idol['fans_count'] + $addOwnerCount, $idolId);
                }
            }
            // 动态列表
            $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('activity');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZADD($cacheKey, strtotime('now'), $idolId);
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

        $list = $cartoonRoleRepository->list($ids);
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
            'name' => 'required|min:1|max:35',
            'alias' => 'required|min:1|max:120',
            'intro' => 'required|min:1|max:400',
            'avatar' => 'required'
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
            if (
                !$bangumiManager->isOwner($bangumiId, $userId) &&
                $cartoonRole['boss_id'] != $userId
            )
            {
                return $this->resErrRole();
            }
            $lastEditAt = $cartoonRole['last_edit_at'];
            if ($lastEditAt && strtotime($lastEditAt) > strtotime('1 week ago'))
            {
                return $this->resErrBad('一周只能修改一次');
            }
        }

        $alias = Purifier::clean($request->get('alias'));
        $alias = $alias ? $alias : $cartoonRole['alias'];
        $data = [
            'bangumi_id' => $bangumiId,
            'avatar' => $request->get('avatar'),
            'name' => Purifier::clean($request->get('name')),
            'intro' => Purifier::clean($request->get('intro')),
            'alias' => $alias,
            'state' => $userId,
            'last_edit_at' => Carbon::now()
        ];
        $stock_price = $request->get('stock_price');
        if ($stock_price)
        {
            $data['stock_price'] = $stock_price;
        }
        $max_stock_count = $request->get('max_stock_count');
        if ($max_stock_count)
        {
            $data['max_stock_count'] = $max_stock_count;
        }

        CartoonRole
            ::where('id', $id)
            ->update($data);

        $cartoonRoleRepository->migrateSearchIndex('U', $id);

        Redis::DEL($cartoonRoleRepository->idolItemCacheKey($id));
        if ($data['stock_price'] || $data['max_stock_count'])
        {
            $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('stock_price');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZADD($cacheKey, $data['stock_price'], $id);
            }
            $cacheKey = $cartoonRoleRepository->marketIdolListCacheKey('activity');
            if (Redis::EXISTS($cacheKey))
            {
                Redis::ZADD($cacheKey, strtotime('now'), $id);
            }
        }

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
    public function ban(Request $request)
    {
        $id = $request->get('id');
        $bangumiId = $request->get('bangumi_id');

        DB::table('cartoon_role')
            ->where('id', $id)
            ->update([
                'state' => 0,
                'deleted_at' => Carbon::now()
            ]);

        $cartoonRoleTrendingService = new CartoonRoleTrendingService($bangumiId);
        $cartoonRoleTrendingService->delete($id);

        $job = (new \App\Jobs\Search\Index('D', 'role', $id));
        dispatch($job);

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
