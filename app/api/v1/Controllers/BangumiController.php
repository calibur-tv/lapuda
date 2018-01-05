<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Models\Bangumi;
use App\Models\BangumiTag;
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\TagRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BangumiController extends Controller
{
    public function timeline(Request $request)
    {
        $year = intval($request->get('year'));
        $take = $request->get('take') ?: 3;
        if (!$year)
        {
            return $this->resErr(['请求参数错误']);
        }

        $repository = new BangumiRepository();
        $data = [];

        for ($i = 0; $i < $take; $i++) {
            $data = array_merge($data, $repository->timeline($year - $i));
        }

        return $this->resOK([
            'data' => $data,
            'min' => $repository->timelineMinYear()
        ]);
    }

    public function released()
    {
        $data = Cache::remember('bangumi_release_list', config('cache.ttl'), function ()
        {
            $ids = Bangumi::where('released_at', '<>', 0)->pluck('id');

            $repository = new BangumiRepository();
            $list = $repository->list($ids);

            $result = [
                [], [], [], [], [], [], []
            ];
            foreach ($list as $item)
            {
                $item['update'] = time() - $item['released_time'] < 604800;
                $id = $item['released_at'];
                isset($result[$id]) ? $result[$id][] = $item : $result[$id] = [$item];
            }

            return $result;
        });

        return $this->resOK($data);
    }

    public function show($id)
    {
        $repository = new BangumiRepository();
        $bangumi = $repository->item($id);

        $bangumi['followers'] = $repository->getFollowers($id);

        $user = $this->getAuthUser();

        $bangumi['followed'] = is_null($user)
            ? false
            : $repository->checkUserFollowed($user->id, $id);

        $transformer = new BangumiTransformer();

        return $this->resOK($transformer->show($bangumi));
    }

    public function videos($id)
    {
        $repository = new BangumiRepository();
        $bangumi = $repository->item($id);

        return $this->resOK($repository->videos($id, json_decode($bangumi['season'])));
    }

    public function tags()
    {
        $tagRepository = new TagRepository();

        return $this->resOK($tagRepository->all(0));
    }

    public function category(Request $request)
    {
        $tags = $request->get('id');
        $page = $request->get('page') ?: 1;

        if (is_null($tags))
        {
            return $this->resErr(['请求参数不能为空'], 422);
        }

        // 格式化为数组 -> 只保留数字 -> 去重 -> 保留value
        $tags = array_values(array_unique(array_filter(explode('-', $tags), function ($tag) {
            return !preg_match("/[^\d-., ]/", $tag);
        })));

        if (empty($tags))
        {
            return $this->resErr(['请求参数格式错误'], 422);
        }

        sort($tags);
        $repository = new BangumiRepository();

        return $this->resOK($repository->category($tags, $page));
    }

    public function follow($id)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['用户认证失败'], 404);
        }

        $bangumiRepository = new BangumiRepository();
        $followed = $bangumiRepository->toggleFollow($user->id, $id);

        return $this->resOK($followed);
    }

    public function posts(Request $request, $id)
    {
        $user = $this->getAuthUser();
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;
        $type = $request->get('type') ?: 'new';

        $bangumiRepository = new BangumiRepository();
        $ids = $bangumiRepository->getPostIds($id, $type);

        if (empty($ids))
        {
            $list = [];
        }
        else
        {
            $postRepository = new PostRepository();
            $list = $postRepository->list(array_slice(array_diff($ids, $seen), 0, $take));
        }

        $transformer = new PostTransformer();

        return $this->resOK([
            'data' => $transformer->bangumi($list),
            'total' => count($ids)
        ]);
    }
}
