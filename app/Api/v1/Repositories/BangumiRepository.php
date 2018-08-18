<?php

namespace App\Api\V1\Repositories;

use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Bangumi;
use App\Models\BangumiFollow;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use App\Services\OpenSearch\Search;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

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
            $season = json_decode($bangumi['season']);

            if ($bangumi['released_video_id'])
            {
                $part = Video::where('id', $bangumi['released_video_id'])->pluck('part')->first();
                // 如果有季度信息，并且 name 和 part 都存在，那么就要重算 released_part
                if ($season !== '' && isset($season->part) && isset($season->name))
                {
                    // 如果设置了 re（重拍），那么就要计算
                    if (isset($season->re))
                    {
                        $reset = $season->re;
                        // 如果 re 是一个数组
                        if (gettype($reset) === 'array')
                        {
                            // 假设有 4季，第二三季是连着的
                            // season->re：[1, 1, 0]
                            // season->part: [0, 12, 24, -1]
                            // $part 可能是 10, 20, 40
                            // 我们希望得到的结果是：10, 8, 28
                            foreach ($season->part as $i => $val)
                            {
                                // 遇到第一个大于当前 $part 的数字或者遇到 -1
                                if ($val > $part || $val === -1)
                                {
                                    // 从后向前遍历
                                    for ($j = $i; $j >= 0; $j--)
                                    {
                                        // 遇到第一个需要 reset 的，就 reset
                                        if ($reset[$j])
                                        {
                                            $bangumi['released_part'] = $part - $season->part[$j];
                                            break;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        else
                        {
                            // re 是 0 或 1
                            if ($reset) // 是 1，需要重排
                            {
                                // part 必须是升序排列的，从 0 开始，当番剧未完结时，最后一位是 -1
                                // 遍历 part
                                // 比如：[1, 24, 50, -1]
                                // 我们获取到的 $part  是某个集数，可能是 52 或 26
                                foreach ($season->part as $i => $val)
                                {
                                    // 遇到第一个大于等于当前 $part 的数字或者遇到 -1
                                    if ($val >= $part || $val === -1)
                                    {
                                        // 减去上一季度part的值
                                        $bangumi['released_part'] = $part - $season->part[$i - 1];
                                        break;
                                    }
                                }
                            }
                            else
                            {
                                $bangumi['released_part'] = $part;
                            }
                        }
                    }
                    else
                    {
                        // 没有设置 re，不用计算
                        $bangumi['released_part'] = $part;
                    }
                }
                else
                {
                    $bangumi['released_part'] = $part;
                }
            }
            else
            {
                // 如果这个番剧是连载的，但是没有传过视频，则 released_part 是 0
                $bangumi['released_part'] = 0;
            }

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

    public function videos($id, $season)
    {
        return $this->Cache('bangumi_'.$id.'_videos', function () use ($id, $season)
        {
            $list = Video::where('bangumi_id', $id)
                ->orderBy('part', 'ASC')
                ->select('id', 'name', 'poster', 'part')
                ->get()
                ->toArray();

            $hasSeason = $season !== '' && isset($season->part) && isset($season->name);
            if ($hasSeason)
            {
                usort($list, function($prev, $next)
                {
                    return $prev['part'] - $next['part'];
                });

                $part = $season->part;
                $time = $season->time;
                $name = $season->name;

                $videos = [];
                $resetPart = isset($season->re);
                for ($i=0, $j=1; $j < count($part); $i++, $j++)
                {
                    $begin = $part[$i];
                    $length = $part[$j] - $begin;
                    $reset = $resetPart ? (gettype($season->re) === 'array' ? $season->re[$i] : $season->re) : false;
                    array_push($videos, [
                        'name' => $name[$i],
                        'time' => $time[$i],
                        'base' => $reset && $i ? $part[$i] : 0,
                        'data' => $length > 0 ? array_slice($list, $begin, $length) : array_slice($list, $begin)
                    ]);
                }
            }
            else
            {
                $videos = [
                    [
                        'data' => $list,
                        'base' => 0,
                        'time' => '',
                        'name' => ''
                    ]
                ];
            }

            return [
                'videos' => $videos,
                'has_season' => $hasSeason,
                'total' => count($list)
            ];
        });
    }

    public function getTopPostIds($id)
    {
        return $this->RedisList('bangumi_'.$id.'_posts_top_ids', function () use ($id)
        {
            return Post::where('bangumi_id', $id)
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
}