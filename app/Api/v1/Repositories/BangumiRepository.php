<?php

namespace App\Api\V1\Repositories;

use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Models\Bangumi;
use App\Models\Post;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

class BangumiRepository extends Repository
{
    public function item($id)
    {
        if (!$id)
        {
            return null;
        }

        $bangumi = $this->RedisHash('bangumi_'.$id, function () use ($id)
        {
            $bangumi = Bangumi::find($id);
            if (is_null($bangumi))
            {
                return null;
            }
            $bangumi = $bangumi->toArray();

            $bangumi['alias'] = $bangumi['alias'] === 'null' ? '' : json_decode($bangumi['alias'])->search;

            return $bangumi;
        });

        if (is_null($bangumi))
        {
            return null;
        }

        return $bangumi;
    }

    public function timeline($year)
    {
        return $this->Cache('bangumi_news_' . $year, function () use ($year)
        {
            $begin = mktime(0, 0, 0, 1, 1, $year);
            $end = mktime(0, 0, 0, 1, 1, $year + 1);
            $ids = Bangumi::whereRaw('published_at >= ? and published_at < ?', [$begin, $end])
                ->latest('published_at')
                ->pluck('id');

            $bangumiRepository = new BangumiRepository();
            $bangumiTransformer = new BangumiTransformer();
            $list = $bangumiRepository->list($ids);

            $result = [];
            foreach ($list as $item)
            {
                $id = date('Y 年 m 月', $item['published_at']);
                $item['timeline'] = $id;
                isset($result[$id]) ? $result[$id][] = $item : $result[$id] = [$item];
            }

            $keys = array_keys($result);
            $values = array_values($result);
            $count = count(array_keys($result));
            $cache = [];
            for ($i = 0; $i < $count; $i++)
            {
                $cache[$i] = [
                    'date' => $keys[$i],
                    'list' => $bangumiTransformer->timeline($values[$i])
                ];
            }

            return $cache;
        });
    }

    public function timelineMinYear()
    {
        return $this->RedisItem('bangumi_news_year_min', function ()
        {
            return date('Y', Bangumi::where('published_at', '<>', '0')->min('published_at'));
        });
    }

    public function videos($id)
    {
        return $this->Cache("bangumi_{$id}_videos", function () use ($id)
        {
            $videoRepository = new VideoRepository();
            $bangumiSeasonRepository = new BangumiSeasonRepository();
            $seasons = $bangumiSeasonRepository->listByBangumiId($id);
            array_walk($seasons, function (&$season) use ($videoRepository)
            {
                $videoIds = $season['videos'] ? explode(',', $season['videos']) : [];
                $season = [
                    'name' => $season['name'],
                    'time' => date('Y.m', $season['published_at']),
                ];
                $videos = $videoRepository->list($videoIds);
                foreach ($videos as $video)
                {
                    $season['videos'][] = [
                        'id' => $video['id'],
                        'name' => $video['name'],
                        'poster' => $video['poster'],
                        'episode' => $video['episode'],
                    ];
                }
            });

            return $seasons;
        });
    }

    public function getTopPostIds($id)
    {
        return $this->RedisList('bangumi_'.$id.'_posts_top_ids', function () use ($id)
        {
            return Post
                ::where('bangumi_id', $id)
                ->whereNotNull('top_at')
                ->orderBy('top_at', 'DESC')
                ->pluck('id');
        });
    }

    public function category($tags, $page)
    {
        return $this->Cache('bangumi_tags_' . implode('_', $tags) . '_page_'.$page, function () use ($tags, $page)
        {
            $take = 10;
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
            $temp = array_count_values(
                DB::table('bangumi_tag_relations')
                    ->whereIn('tag_id', $tags)
                    ->orderBy('id')
                    ->pluck('model_id')
                    ->toArray()
            );

            $data = [];
            foreach ($temp as $id => $c)
            {
                // 因此当 count(B) === count($tags) 时，就是我们要的
                if ($c === $count)
                {
                    $data[] = $id;
                }
            }

            $ids = array_slice($data, $page * $take, $take);
            $total = count($data);

            $transformer = new BangumiTransformer();
            return [
                'list' => $transformer->category($this->list($ids)),
                'noMore' => $total - ($take * ($page + 1)) <= 0,
                'total' => $total
            ];
        });
    }

    public function panel($bangumiId, $userId)
    {
        $bangumi = $this->item($bangumiId);
        if (is_null($bangumi))
        {
            return null;
        }

        if ($userId)
        {
            $bangumiFollowService = new BangumiFollowService();
            $bangumiManager = new BangumiManager();
            $bangumi['followed'] = $bangumiFollowService->check($userId, $bangumiId);
            $bangumi['is_master'] = $bangumiManager->isOwner($bangumiId, $userId);
        }
        else
        {
            $bangumi['followed'] = false;
            $bangumi['is_master'] = false;
        }

        $transformer = new BangumiTransformer();
        return $transformer->panel($bangumi);
    }

    public function searchAll()
    {
        return $this->Cache('bangumi_all_list', function ()
        {
            $bangumis = Bangumi::select('id', 'name', 'avatar', 'alias')
                ->orderBy('id', 'DESC')
                ->get()
                ->toArray();

            foreach ($bangumis as $i => $item)
            {
                $bangumis[$i]['alias'] = $item['alias'] === 'null' ? '' : json_decode($item['alias'])->search;
            }

            return $bangumis;
        });
    }

    public function appendBangumiToList($list)
    {
        $result = [];
        foreach ($list as $item)
        {
            $bangumi = $this->item($item['bangumi_id']);
            if (is_null($bangumi))
            {
                continue;
            }
            $item['bangumi'] = $bangumi;
            $result[] = $item;
        }

        return $result;
    }

    public function migrateSearchIndex($type, $id, $async = true)
    {
        $type = $type === 'C' ? 'C' : 'U';
        $bangumi = $this->item($id);
        $content = $bangumi['alias'];

        $job = (new \App\Jobs\Search\Index($type, 'bangumi', $id, $content));
        dispatch($job);
    }
}