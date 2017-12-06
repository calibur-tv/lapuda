<?php

namespace App\Http\Controllers;

use App\Models\Bangumi;
use App\Models\BangumiTag;
use App\Models\User;
use App\Repositories\BangumiRepository;
use App\Repositories\TagRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BangumiController extends Controller
{
    public function news()
    {
        $data = Cache::remember('bangumi_news_page_1', config('cache.ttl'), function ()
        {
            $ids = Bangumi::latest('published_at')->get()->pluck('id');

            $repository = new BangumiRepository();

            return $repository->list($ids);
        });

        return $this->resOK($data);
    }

    public function show($id)
    {
        $result = Cache::remember('bangumi_'.$id.'_show', config('cache.ttl'), function () use ($id)
        {
            $repository = new BangumiRepository();
            $bangumi = $repository->item($id);

            if (is_null($bangumi)) {
                return null;
            }

            $bangumi->videoPackage = $repository->videos($bangumi);

            $user = $this->getAuthUser();
            $bangumi->followed = is_null($user)
                ? false
                : $repository->checkUserFollowed($user->id, $id);
            $bangumi->followers = $repository->getFollowers($id);

            return $bangumi;
        });

        return is_null($result)
            ? $this->resErr(['番剧不存在'])
            : $this->resOK($result);
    }

    public function tags(Request $request)
    {
        $tagRepository = new TagRepository();
        $data = [
            'tags' => $tagRepository->all(0)
        ];
        $bangumis = [];

        $tags = $request->get('id');

        if ($tags !== null)
        {
            // 格式化为数组 -> 只保留数字 -> 去重 -> 保留value
            $tags = array_values(array_unique(array_filter(explode('-', $tags), function ($tag) {
                return !preg_match("/[^\d-., ]/", $tag);
            })));

            if ( ! empty($tags))
            {
                sort($tags);
                $bangumi_id = Cache::remember('bangumi_tags_' . implode('_', $tags), config('cache.ttl'), function () use ($tags)
                {
                    $count = count($tags);
                    // bangumi 和 tags 是多对多的关系
                    // 这里通过一个 tag_id Array 拿到一个 bangumi_id 的 Array
                    // bangumi_id Array 中，同一个 bangumi_id 会重复出现
                    // tags_id = [1, 2, 3]
                    // bangumi_id 可能是
                    // A 命中 1
                    // B 命中 1, 2, 3
                    // C 命中 1, 3
                    // 我们要拿的是 B，而 ids 是：[A, B, B, B, C, C]
                    $ids = array_count_values(BangumiTag::whereIn('tag_id', $tags)->pluck('bangumi_id')->toArray());
                    $result = [];
                    foreach ($ids as $id => $c)
                    {
                        // 因此当 count(B) === count($tags) 时，就是我们要的
                        if ($c === $count)
                        {
                            array_push($result, $id);
                        }
                    }
                    return $result;
                });

                if ( ! empty($bangumi_id))
                {
                    $bangumiRepository = new BangumiRepository();
                    foreach ($bangumi_id as $id)
                    {
                        array_push($bangumis, $bangumiRepository->item($id));
                    }
                }
            }
        }
        $data['bangumis'] = $bangumis;

        return $this->resOK($data);
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

    public function posts(Request $request)
    {
        // TODO：使用 Redis list 做缓存
        // TODO：使用 seen_ids 做分页
        // TODO：应该 new 一个 PostRepository，有一个 listByBangumi 的方法
    }
}
