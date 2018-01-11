<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\PostRepository;
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
            return $this->res([]);
        }

        $userId = $this->getAuthUserId();
        $list = $repository->list(array_slice(array_diff($ids, $seen), 0, $take));

        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $userId ? $repository->checkPostLiked($item['id'], $userId) : false;
        }

        $transformer = new PostTransformer();

        return $this->res($transformer->trending($list));
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
            return $this->res([]);
        }

        $userId = $this->getAuthUserId();
        $list = $repository->list(array_slice(array_diff($ids, $seen), 0, $take));

        foreach ($list as $i => $item)
        {
            $list[$i]['liked'] = $userId ? $repository->checkPostLiked($item['id'], $userId) : false;
        }

        $transformer = new PostTransformer();

        return $this->res($transformer->trending($list));
    }
}
