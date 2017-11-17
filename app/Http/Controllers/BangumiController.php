<?php

namespace App\Http\Controllers;

use App\Models\Bangumi;
use App\Models\BangumiTag;
use App\Models\Tag;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BangumiController extends Controller
{
    public function news()
    {
        $data = Cache::remember('bangumi_news_page_1', config('cache.ttl'), function ()
        {
            $ids = Bangumi::latest('published_at')->get()->pluck('id');
            $result = [];
            // 这里应该用 whereIn 而不是多次 where
            foreach ($ids as $id)
            {
                array_push($result, $this->getBangumiInfoById($id));
            }
            return $result;
        });

        return $data;
    }

    public function show($id)
    {
        return Cache::remember('bangumi_info_' . $id, config('cache.ttl'), function () use ($id)
        {
            $bangumi = $this->getBangumiInfoById($id);
            $bangumi->videoPackage = $this->getBangumiVideo($bangumi);

            return $bangumi;
        });
    }

    public function tags(Request $request)
    {
        $list = Cache::remember('bangumi_tags_all', config('cache.ttl'), function ()
        {
            return Tag::where('model', 0)->select('id', 'name')->get()->toArray();
        });
        $data = [
            'tags' => $list
        ];
        $bangumis = [];

        $tags = $request->get('id');

        if ($tags !== null)
        {
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
                    foreach ($bangumi_id as $id)
                    {
                        array_push($bangumis, $this->getBangumiInfoById($id));
                    }
                }
            }
        }
        $data['bangumis'] = $bangumis;

        return response()->json($data, 200);
    }

    protected function getBangumiInfoById($id)
    {
        return Cache::remember('bangumi_tags_'.$id, config('cache.ttl'), function () use ($id)
        {
            $bangumi = Bangumi::find($id);
            // 这里可以使用 LEFT-JOIN 语句优化
            $bangumi->released_part = $bangumi->released_video_id
                ? Video::find($bangumi->released_video_id)->pluck('part')
                : 0;
            $bangumi->tags = $this->getBangumiTags($bangumi);
            // json 格式化
            $bangumi->alias = $bangumi->alias === 'null' ? '' : json_decode($bangumi->alias);
            $bangumi->season = $bangumi->season === 'null' ? '' : json_decode($bangumi->season);

            return $bangumi;
        });
    }

    protected function getBangumiVideo($bangumi)
    {
        return Cache::remember('bangumi_video_'.$bangumi->id, config('cache.ttl'), function () use ($bangumi)
        {
            $season = $bangumi['season'] === 'null' ? '' : json_decode($bangumi['season']);

            $list = Cache::remember('video_groupBy_bangumi_'.$bangumi->id, config('cache.ttl'), function () use ($bangumi)
            {
                return Video::where('bangumi_id', $bangumi->id)->get()->toArray();
            });

            if ($season !== '' && isset($season->part) && isset($season->name))
            {
                usort($list, function($prev, $next) {
                    return $prev['part'] - $next['part'];
                });
                $part = $season->part;
                $time = $season->time;
                $name = $season->name;
                $videos = [];
                for ($i=0, $j=1; $j < count($part); $i++, $j++) {
                    $begin = $part[$i];
                    $length = $part[$j] - $begin;
                    array_push($videos, [
                        'name' => $name[$i],
                        'time' => $time[$i],
                        'data' => $length > 0 ? array_slice($list, $begin, $length) : array_slice($list, $begin)
                    ]);
                }
                $repeat = isset($season->re) ? (boolean)$season->re : false;
            } else {
                $videos = $list;
                $repeat = false;
            }

            return [
                'videos' => $videos,
                'repeat' => $repeat
            ];
        });
    }

    protected function getBangumiTags($bangumi)
    {
        return Cache::remember('bangumi_tags_'.$bangumi->id, config('cache.ttl'), function () use ($bangumi)
        {
            // 这个可以使用 LEFT-JOIN 语句优化
            return $bangumi->tags()->get()->transform(function ($item) {
                return [
                    'id' => $item->pivot->tag_id,
                    'name' => $item->name
                ];
            });
        });
    }
}
