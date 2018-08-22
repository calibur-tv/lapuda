<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Trending\RoleTrendingService;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use App\Services\OpenSearch\Search;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("动漫角色相关接口")
 */
class CartoonRoleController extends Controller
{
    /**
     * 给角色应援
     *
     * @Post("/cartoon_role/`roleId`/star")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     *
     * @Transaction({
     *      @Response(204),
     *      @Response(404, body={"code": 40401, "message": "不存在的角色"}),
     *      @Response(403, body={"code": 40301, "message": "没有足够的金币"})
     * })
     */
    public function star($id)
    {
        if (!CartoonRole::where('id', $id)->count())
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        $userRepository = new UserRepository();

        if (!$userRepository->toggleCoin(false, $userId, 0, 3, $id))
        {
            return $this->resErrRole('没有足够的金币');
        }

        $cartoonRoleRepository = new CartoonRoleRepository();

        if ($cartoonRoleRepository->checkHasStar($id, $userId))
        {
            CartoonRoleFans::whereRaw('role_id = ? and user_id = ?', [$id, $userId])->increment('star_count');

            $trendingKey = 'cartoon_role_trending_' . $id;

            if (Redis::EXISTS('cartoon_role_'.$id))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'star_count', 1);
            }
            if (Redis::EXISTS($trendingKey))
            {
                Redis::HINCRBYFLOAT($trendingKey, 'star_count', 1);
            }
        }
        else
        {
            CartoonRoleFans::create([
                'role_id' => $id,
                'user_id' => $userId,
                'star_count' => 1
            ]);

            CartoonRole::where('id', $id)->increment('fans_count');

            $trendingKey = 'cartoon_role_trending_' . $id;

            if (Redis::EXISTS('cartoon_role_'.$id))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'fans_count', 1);
                Redis::HINCRBYFLOAT('cartoon_role_'.$id, 'star_count', 1);
            }
            if (Redis::EXISTS($trendingKey))
            {
                Redis::HINCRBYFLOAT($trendingKey, 'fans_count', 1);
                Redis::HINCRBYFLOAT($trendingKey, 'star_count', 1);
            }
        }

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

        CartoonRole::where('id', $id)->increment('star_count');

        return $this->resNoContent();
    }

    /**
     * 角色的粉丝列表
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
     * 角色详情
     *
     * @Get("/cartoon_role/`roleId`/show")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"bangumi": "番剧简介", "data": "角色信息"}}),
     *      @Response(404, body={"code": 40401, "message": "不存在的角色"}),
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

        $role['lover'] = $role['loverId'] ? $userTransformer->item($userRepository->item($role['loverId'])) : null;
        $role['hasStar'] = $cartoonRoleRepository->checkHasStar($role['id'], $userId);

        $cartoonTransformer = new CartoonRoleTransformer();

        $searchService = new Search();
        if ($searchService->checkNeedMigrate('role', $id))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('role', $id));
            dispatch($job);
        }

        return $this->resOK($cartoonTransformer->show([
            'bangumi' => $bangumi,
            'data' => $role
        ]));
    }

    public function adminShow(Request $request)
    {
        $result = CartoonRole::find($request->get('id'));

        return $this->resOK($result);
    }

    public function create(Request $request)
    {
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

        $name = Purifier::clean($request->get('name'));
        $alias = Purifier::clean($request->get('alias'));
        $time = Carbon::now();

        $count = CartoonRole::whereRaw('bangumi_id = ? and name = ?', [$bangumiId, $name])
            ->count();

        if ($count)
        {
            return $this->resErrBad('该角色可能已存在');
        }

        $id =  CartoonRole::insertGetId([
            'bangumi_id' => $bangumiId,
            'avatar' => $request->get('avatar'),
            'name' => $name,
            'intro' => Purifier::clean($request->get('intro')),
            'alias' => $alias,
            'state' => $userId,
            'created_at' => $time,
            'updated_at' => $time
        ]);

        $cartoonRoleRepository = new CartoonRoleRepository();
        $cartoonRoleRepository->migrateSearchIndex('C', $id);

        $cartoonRoleTrendingService = new RoleTrendingService($bangumiId);
        $cartoonRoleTrendingService->create($id);

        return $this->resCreated($id);
    }

    public function edit(Request $request)
    {
        $user = $this->getAuthUser();
        $userId = $user->id;
        $bangumiId = $request->get('bangumi_id');
        $id = $request->get('id');

        if (!$user->is_admin)
        {
            $bangumiManager = new BangumiManager();
            if (!$bangumiManager->isOwner($bangumiId, $userId))
            {
                return $this->resErrRole();
            }
        }

        $alias = Purifier::clean($request->get('alias'));

        CartoonRole::where('id', $id)->update([
            'bangumi_id' => $bangumiId,
            'avatar' => $request->get('avatar'),
            'name' => Purifier::clean($request->get('name')),
            'intro' => Purifier::clean($request->get('intro')),
            'alias' => $alias,
            'state' => $userId
        ]);

        $cartoonRoleRepository = new CartoonRoleRepository();
        $cartoonRoleRepository->migrateSearchIndex('U', $id);

        Redis::DEL('cartoon_role_' . $id);

        return $this->resNoContent();
    }

    public function trials()
    {
        $roles = CartoonRole::where('state', '<>', 0)
            ->select('id', 'state', 'name', 'bangumi_id')
            ->get();

        return $this->resOK($roles);
    }

    public function ban(Request $request)
    {
        $id = $request->get('id');
        $bangumiId = $request->get('bangumi_id');

        CartoonRole::where('id', $id)->delete();

        $cartoonRoleTrendingService = new RoleTrendingService($bangumiId);
        $cartoonRoleTrendingService->delete($id);

        $job = (new \App\Jobs\Search\Index('D', 'role', $id));
        dispatch($job);

        return $this->resNoContent();
    }

    public function pass(Request $request)
    {
        CartoonRole::where('id', $request->get('id'))
            ->update([
                'state' => 0
            ]);

        return $this->resNoContent();
    }
}
