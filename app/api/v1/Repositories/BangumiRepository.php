<?php

namespace App\Api\V1\Repositories;

use App\Api\V1\Transformers\BangumiTransformer;
use App\Models\Bangumi;
use App\Models\BangumiFollow;
use App\Models\BangumiTag;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BangumiRepository extends Repository
{
    public function item($id)
    {
        $bangumi = $this->RedisHash('bangumi_'.$id, function () use ($id)
        {
            $bangumi = Bangumi::findOrFail($id)->toArray();
            // 这里可以使用 LEFT-JOIN 语句优化
            $bangumi['released_part'] = $bangumi['released_video_id']
                ? Video::where('id', $bangumi['released_video_id'])->pluck('part')->first()
                : 0;
            return $bangumi;
        });

        $bangumi['tags'] = $this->tags($bangumi['id']);

        return $bangumi;
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->item($id);
        }
        return $result;
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

            $repository = new BangumiRepository();
            $transformer = new BangumiTransformer();
            $list = $repository->list($ids);

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
                    'list' => $transformer->timeline($values[$i])
                ];
            }

            return $cache;
        });
    }

    public function timelineMinYear()
    {
        return $this->Cache('bangumi_news_year_min', function ()
        {
            return intval(date('Y', Bangumi::where('published_at', '<>', '0')->min('published_at')));
        });
    }

    public function checkUserFollowed($user_id, $bangumi_id)
    {
        return (Boolean)BangumiFollow::whereRaw('user_id = ? and bangumi_id = ?', [$user_id, $bangumi_id])->count();
    }

    public function toggleFollow($user_id, $bangumi_id)
    {
        $followed = BangumiFollow::whereRaw('user_id = ? and bangumi_id = ?', [$user_id, $bangumi_id])
            ->pluck('id')
            ->first();

        if (is_null($followed))
        {
            BangumiFollow::create([
                'user_id' => $user_id,
                'bangumi_id' => $bangumi_id
            ]);

            $result = true;
            $num = 1;
        }
        else
        {
            BangumiFollow::find($followed)->delete();

            $result = false;
            $num = -1;
        }

        Bangumi::where('id', $bangumi_id)->increment('count_like', $num);
        if (Redis::EXISTS('bangumi_'.$bangumi_id))
        {
            Redis::HINCRBYFLOAT('bangumi_'.$bangumi_id, 'count_like', $num);
        }

        return $result;
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
                'repeat' => $repeat,
                'total' => count($list)
            ];
        });
    }

    public function tags($bangumiId)
    {
        return $this->Cache('bangumi_'.$bangumiId.'_tags', function () use ($bangumiId)
        {
            $ids = BangumiTag::where('bangumi_id', $bangumiId)
                ->pluck('tag_id')
                ->toArray();

            if (empty($ids))
            {
                return [];
            }

            return Tag::whereIn('id', $ids)
                ->select('id', 'name')
                ->get()
                ->toArray();
        });
    }

    public function getFollowers($bangumiId)
    {
        return Cache::remember('bangumi_'.$bangumiId.'_followers', config('cache.ttl'), function () use ($bangumiId)
        {
            $ids = BangumiFollow::where('bangumi_id', $bangumiId)->pluck('user_id');
            if (empty($ids))
            {
                return [];
            }

            return User::whereIn('id', $ids)
                ->select('id', 'avatar', 'zone', 'nickname')
                ->get()
                ->toArray();
        });
    }

    public function getPostIds($id, $type)
    {
        $postRepository = new PostRepository();
        $cacheKey = $postRepository->bangumiListCacheKey($id, $type);

        return $this->RedisSort($cacheKey, function () use ($id)
        {
            return Post::where('bangumi_id', $id)
                ->orderBy('id', 'desc')
                ->pluck('updated_at', 'id');

        }, true);
    }

    public function category($tags, $page)
    {
        return $this->Cache('bangumi_tags_' . implode('_', $tags) . '_page_'.$page, function () use ($tags, $page)
        {
            $take = config('website.list_count');
            $start = ($page - 1) * $take;
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
                BangumiTag::whereIn('tag_id', $tags)
                    ->orderBy('id')
                    ->pluck('bangumi_id')
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

            $ids = array_slice($data, $start, $take);

            $transformer = new BangumiTransformer();
            return $transformer->category($this->list($ids));
        });
    }
}