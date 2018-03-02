<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\PostTransformer;
use Illuminate\Http\Request;

/**
 * @Resource("排行相关接口")
 */
class TrendingController extends Controller
{
    /**
     * 最新帖子列表
     *
     * @Post("/trending/post/new")
     *
     * @Transaction({
     *      @Request({"take": "获取数量", "seenIds": "看过的postIds, 用','号分割的字符串"}, headers={"Authorization": "Bearer JWT-Token"}, identifier="A"),
     *      @Response(200, body={"code": 0, {"data": "帖子列表"}}),
     * })
     */
    public function postNew(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $repository = new PostRepository();
        $ids = $repository->getNewIds();

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $userId = $this->getAuthUserId();
        $list = $repository->list(array_slice(array_diff($ids, $seen), 0, $take));

        foreach ($list as $i => $item)
        {
            if ($userId)
            {
                $id = $item['id'];
                $list[$i]['liked'] = $repository->checkPostLiked($id, $userId);
                $list[$i]['marked'] = $repository->checkPostMarked($id, $userId);
                $list[$i]['commented'] = $repository->checkPostCommented($id, $userId);
            }
            else
            {
                $list[$i]['liked'] = false;
                $list[$i]['marked'] = false;
                $list[$i]['commented'] = false;
            }
        }

        $transformer = new PostTransformer();

        return $this->resOK($transformer->trending($list));
    }

    /**
     * 热门帖子列表
     *
     * @Post("/trending/post/hot")
     *
     * @Transaction({
     *      @Request({"take": "获取数量", "seenIds": "看过的postIds, 用','号分割的字符串"}, headers={"Authorization": "Bearer JWT-Token"}, identifier="A"),
     *      @Response(200, body={"code": 0, {"data": "帖子列表"}}),
     * })
     */
    public function postHot(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $repository = new PostRepository();
        $ids = $repository->getHotIds();

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $userId = $this->getAuthUserId();
        $list = $repository->list(array_slice(array_diff($ids, $seen), 0, $take));

        foreach ($list as $i => $item)
        {
            if ($userId)
            {
                $id = $item['id'];
                $list[$i]['liked'] = $repository->checkPostLiked($id, $userId);
                $list[$i]['marked'] = $repository->checkPostMarked($id, $userId);
                $list[$i]['commented'] = $repository->checkPostCommented($id, $userId);
            }
            else
            {
                $list[$i]['liked'] = false;
                $list[$i]['marked'] = false;
                $list[$i]['commented'] = false;
            }
        }

        $transformer = new PostTransformer();

        return $this->resOK($transformer->trending($list));
    }

    public function cartoonRole(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $repository = new CartoonRoleRepository();

        $ids = array_slice(array_diff($repository->trendingIds(), $seen), 0, config('website.list_count'));

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $repository->trendingItem($id);
        }

        $transformer = new CartoonRoleTransformer();

        return $this->resOK($transformer->trending($result));
    }
}
