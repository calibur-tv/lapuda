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
use App\Api\V1\Services\Trending\CartoonRoleTrendingService;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use App\Models\UserCoin;
use App\Services\OpenSearch\Search;
use App\Services\Trial\UserIpAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function star($id)
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $cartoonRole = $cartoonRoleRepository->item($id);
        if (is_null($cartoonRole))
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        $userIpAddress = new UserIpAddress();
        $blocked = $userIpAddress->check($userId);
        if ($blocked)
        {
            return $this->resErrRole('你已被封禁，无权应援');
        }

        $userRepository = new UserRepository();

        if (!$userRepository->toggleCoin(false, $userId, 0, 3, $id))
        {
            return $this->resErrRole('没有足够的团子');
        }

        $cartoonRoleTrendingService = new CartoonRoleTrendingService($cartoonRole['bangumi_id'], $userId);
        $starded = $cartoonRoleRepository->checkHasStar($id, $userId);

        if ($starded)
        {
            CartoonRoleFans
                ::whereRaw('role_id = ? and user_id = ?', [$id, $userId])
                ->increment('star_count');

            if (Redis::EXISTS('cartoon_role_'.$id))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'star_count', 1);
            }
        }
        else
        {
            CartoonRoleFans::create([
                'role_id' => $id,
                'user_id' => $userId,
                'star_count' => 1
            ]);

            $cartoonRoleFansCounter = new CartoonRoleFansCounter();
            $cartoonRoleFansCounter->add($id);

            if (Redis::EXISTS('cartoon_role_'.$id))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'fans_count', 1);
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'star_count', 1);
            }
        }
        $cartoonRoleTrendingService->update($id, $starded);
        // 今日动态榜单
        $cartoonRoleRepository->SortAdd('cartoon_role_today_activity_ids', $id, 1);

        $newCacheKey = 'cartoon_role_' . $id . '_new_fans_ids';
        $hotCacheKey = 'cartoon_role_' . $id . '_hot_fans_ids';

        if (Redis::EXISTS($newCacheKey))
        {
            Redis::ZADD($newCacheKey, strtotime('now'), $userId);
        }
        if (Redis::EXISTS($hotCacheKey))
        {
            Redis::ZINCRBY($hotCacheKey, 1, $userId);
        }

        $cartoonRoleStarCounter = new CartoonRoleStarCounter();
        $cartoonRoleStarCounter->add($id);

        $userActivityService = new UserActivity();
        $userActivityService->update($userId, 3);

        $bangumiActivityService = new BangumiActivity();
        $bangumiActivityService->update($cartoonRole['bangumi_id']);

        return $this->resNoContent();
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

        $role['lover'] = $role['loverId'] ? $userTransformer->item($userRepository->item($role['loverId'])) : null;
        $role['hasStar'] = $cartoonRoleRepository->checkHasStar($role['id'], $userId);

        $cartoonTransformer = new CartoonRoleTransformer();

        $searchService = new Search();
        if ($searchService->checkNeedMigrate('role', $id))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('role', $id));
            dispatch($job);
        }
        $role['star_count'] = $cartoonRoleStarCounter->get($id);
        $role['fans_count'] = $cartoonRoleFansCounter->get($id);

        return $this->resOK($cartoonTransformer->show([
            'bangumi' => $bangumi,
            'data' => $role
        ]));
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
            return UserCoin::where('type', 3)
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
            return UserCoin::where('type', 3)
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
            'intro' => 'required|min:1|max:200',
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

        Redis::DEL('cartoon_role_' . $id);

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

        foreach ($userIds as $userId)
        {
            CartoonRoleFans
                ::where('user_id', $userId)
                ->delete();
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
}
