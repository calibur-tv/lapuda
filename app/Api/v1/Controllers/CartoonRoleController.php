<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\ImageTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Bangumi;
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
     * 番剧角色列表
     *
     * @Get("/bangumi/`bangumiId`/roles")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "角色列表"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的番剧"})
     * })
     */
    public function listOfBangumi(Request $request, $bangumiId)
    {
        if (!Bangumi::where('id', $bangumiId)->count())
        {
            return $this->resErrNotFound('不存在的番剧');
        }

        $cartoonRoleRepository = new CartoonRoleRepository();
        $ids = $cartoonRoleRepository->bangumiOfIds($bangumiId);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $roles = $cartoonRoleRepository->list($ids);
        $userRepository = new UserRepository();
        $userId = $this->getAuthUserId();

        foreach ($roles as $i => $item)
        {
            if ($item['loverId'])
            {
                $roles[$i]['lover'] = $userRepository->item($item['loverId']);
                $roles[$i]['loveMe'] = $userId === intval($item['loverId']);
            }
            else
            {
                $roles[$i]['lover'] = null;
                $roles[$i]['loveMe'] = false;
            }
            $roles[$i]['hasStar'] = $cartoonRoleRepository->checkHasStar($item['id'], $userId);
        }

        $transformer = new CartoonRoleTransformer();

        return $this->resOK($transformer->bangumi($roles));
    }

    /**
     * 给角色应援
     *
     * @Post("/bangumi/`bangumiId`/role/`roleId`/star")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"})
     *
     * @Transaction({
     *      @Response(204),
     *      @Response(404, body={"code": 40401, "message": "不存在的角色"}),
     *      @Response(403, body={"code": 40301, "message": "没有足够的金币"})
     * })
     */
    public function star(Request $request, $bangumiId, $roleId)
    {
        if (!CartoonRole::where('id', $roleId)->count())
        {
            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();
        $userRepository = new UserRepository();

        if (!$userRepository->toggleCoin(false, $userId, 0, 3, $roleId))
        {
            return $this->resErrRole('没有足够的金币');
        }

        $cartoonRoleRepository = new CartoonRoleRepository();

        if ($cartoonRoleRepository->checkHasStar($roleId, $userId))
        {
            CartoonRoleFans::whereRaw('role_id = ? and user_id = ?', [$roleId, $userId])->increment('star_count');

            $trendingKey = 'cartoon_role_trending_' . $roleId;

            if (Redis::EXISTS('cartoon_role_'.$roleId))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$roleId, 'star_count', 1);
            }
            if (Redis::EXISTS($trendingKey))
            {
                Redis::HINCRBYFLOAT($trendingKey, 'star_count', 1);
            }
        }
        else
        {
            CartoonRoleFans::create([
                'role_id' => $roleId,
                'user_id' => $userId,
                'star_count' => 1
            ]);

            CartoonRole::where('id', $roleId)->increment('fans_count');

            $trendingKey = 'cartoon_role_trending_' . $roleId;

            if (Redis::EXISTS('cartoon_role_'.$roleId))
            {
                Redis::HINCRBYFLOAT('cartoon_role_'.$roleId, 'fans_count', 1);
                Redis::HINCRBYFLOAT('cartoon_role_'.$roleId, 'star_count', 1);
            }
            if (Redis::EXISTS($trendingKey))
            {
                Redis::HINCRBYFLOAT($trendingKey, 'fans_count', 1);
                Redis::HINCRBYFLOAT($trendingKey, 'star_count', 1);
            }
        }

        $newCacheKey = 'cartoon_role_' . $roleId . '_new_fans_ids';
        $hotCacheKey = 'cartoon_role_' . $roleId . '_hot_fans_ids';

        if (Redis::EXISTS($newCacheKey))
        {
            Redis::ZADD($newCacheKey, strtotime('now'), $userId);
        }
        if (Redis::EXISTS($hotCacheKey))
        {
            Redis::ZINCRBY($hotCacheKey, 1, $userId);
        }

        CartoonRole::where('id', $roleId)->increment('star_count');

        return $this->resNoContent();
    }

    // TODO：vote service
    // TODO：API doc
    public function fans(Request $request, $bangumiId, $roleId)
    {
        if (!CartoonRole::where('id', $roleId)->count())
        {
            return $this->resErrNotFound();
        }

        $sort = $request->get('sort') ?: 'new';
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $minId = $request->get('minId') ?: 0;
        $cartoonRoleRepository = new CartoonRoleRepository();
        $ids = $sort === 'new' ? $cartoonRoleRepository->newFansIds($roleId, $minId) : $cartoonRoleRepository->hotFansIds($roleId, $seen);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $userRepository = new UserRepository();
        $users = [];
        $i = 0;
        foreach ($ids as $id => $score)
        {
            $users[] = $userRepository->item($id);
            $users[$i]['score'] = $score;
            $i++;
        }

        $transformer = new CartoonRoleTransformer();

        return $this->resOK($transformer->fans($users));
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
        return $this->resOK($cartoonTransformer->show([
            'bangumi' => $bangumi,
            'data' => $role
        ]));
    }

    // TODO：trending service
    // TODO：API doc
    public function images(Request $request, $id)
    {
        if (!CartoonRole::where('id', $id)->count())
        {
            return $this->resErrNotFound();
        }

        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 12;
        $size = intval($request->get('size')) ?: 0;
        $tags = $request->get('tags') ?: 0;
        $creator = intval($request->get('creator'));
        $sort = $request->get('sort') ?: 'new';

        $repository = new ImageRepository();
        $ids = $repository->getRoleImageIds($id, $seen, $take, $size, $tags, $creator, $sort);

        if (empty($ids))
        {
            return $this->resOK([
                'list' => [],
                'type' => $repository->uploadImageTypes()
            ]);
        }

        $imageTransformer = new ImageTransformer();

        $visitorId = $this->getAuthUserId();
        $list = $repository->list($ids);
        $imageLikeService = new ImageLikeService();

        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $imageLikeService->check($visitorId, $item['id'], $item['user_id']);
        }

        return $this->resOK([
            'list' => $imageTransformer->roleShow($list),
            'type' => $repository->uploadImageTypes()
        ]);
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

        $searchService = new Search();
        $searchService->create(
            $id,
            $alias,
            'role'
        );

        Redis::DEL('bangumi_'.$bangumiId.'_cartoon_role_ids');

        $job = (new \App\Jobs\Push\Baidu('role/' . $id));
        dispatch($job);

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

        $searchService = new Search();
        $searchService->update(
            $id,
            $alias,
            'role'
        );

        $job = (new \App\Jobs\Push\Baidu('role/' . $id, 'update'));
        dispatch($job);

        Redis::DEL('cartoon_role_' . $id);

        return $this->resNoContent();
    }

    public function trialList()
    {
        $roles = CartoonRole::where('state', '<>', 0)
            ->select('id', 'state', 'name', 'bangumi_id')
            ->get();

        return $this->resOK($roles);
    }

    public function trialPass(Request $request)
    {
        CartoonRole::where('id', $request->get('id'))
            ->update([
                'state' => 0
            ]);

        return $this->resNoContent();
    }

    public function trialBan(Request $request)
    {
        $id = $request->get('id');
        $bangumiId = $request->get('bangumi_id');

        CartoonRole::where('id', $id)->delete();
        Redis::DEL('bangumi_'.$bangumiId.'_cartoon_role_ids');

        return $this->resNoContent();
    }
}
