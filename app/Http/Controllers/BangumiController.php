<?php

namespace App\Http\Controllers;

use App\Models\Bangumi;
use App\Models\BangumiTag;
use App\Repositories\BangumiRepository;
use App\Repositories\TagRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BangumiController extends Controller
{
    public function timeline(Request $request)
    {
        $time = intval($request->get('time')) ?: time();
        $year = Carbon::createFromTimestamp($time)->year;

        $data = Cache::remember('bangumi_news_page_' . $year, config('cache.ttl'), function () use ($year)
        {
            $begin = $year === 1970
                ? 0
                : mktime(0, 0, 0, 1, 1, $year);
            $end = $year === 1970
                ? mktime(0, 0, 0, 1, 1, date('Y') + 1)
                : mktime(0, 0, 0, 1, 1, $year + 1);
            $ids = Bangumi::whereRaw('published_at >= ? and published_at < ?', [$begin, $end])
                ->latest('published_at')
                ->pluck('id');

            $repository = new BangumiRepository();
            $list = $repository->list($ids);

            $result = [];
            foreach ($list as $item)
            {
                $id = date('Y 年 m 月', $item['published_at']);
                isset($result[$id]) ? $result[$id][] = $item : $result[$id] = [$item];
            }

            return $result;
        });

        return $this->resOK($data);
    }

    public function released()
    {
        $data = Cache::remember('bangumi_release_list', config('cache.ttl'), function ()
        {
            $ids = Bangumi::where('released_at', '<>', 0)->pluck('id');

            $repository = new BangumiRepository();
            $list = $repository->list($ids);

            $result = [];
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
        $bangumi_id = Bangumi::where('id', $id)->first();
        if (is_null($bangumi_id)) {
            return $this->resErr(['番剧不存在'], 404);
        }

        $repository = new BangumiRepository();
        $bangumi = $repository->item($id);

        $bangumi['videoPackage'] = $repository->videos($id, $bangumi['season']);
        $bangumi['followers'] = $repository->getFollowers($id);

        $user = $this->getAuthUser();

        $bangumi['followed'] = is_null($user)
            ? false
            : $repository->checkUserFollowed($user->id, $id);

        return $this->resOK($bangumi);
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
        $ids = Cache::remember('bangumi_tags_' . implode('_', $tags) . '_page' . $page, config('cache.ttl'), function () use ($tags, $page)
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
            $ids = array_count_values(
                BangumiTag::whereIn('tag_id', $tags)
                    ->skip(($page - 1) * config('website.list_count'))
                    ->take(config('website.list_count'))
                    ->orderBy('id')
                    ->pluck('bangumi_id')
                    ->toArray()
            );

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

        if (empty($ids))
        {
            return $this->resOK();
        }

        $bangumiRepository = new BangumiRepository();

        return $this->resOK($bangumiRepository->list($ids));
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
        // TODO：使用 seen_ids 做分页
        // TODO：应该 new 一个 PostRepository，有一个 list 的方法，接收 ids 做参数
    }
}
