<?php

namespace App\Repositories;

use App\Models\Bangumi;
use App\Models\BangumiFollow;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BangumiRepository extends Repository
{
    public function item($id)
    {
        return Cache::remember('bangumi_'.$id.'_show', config('cache.ttl'), function () use ($id)
        {
            $bangumi = Bangumi::find($id);
            // 这里可以使用 LEFT-JOIN 语句优化
            $bangumi->released_part = $bangumi->released_video_id
                ? Video::where('id', $bangumi->released_video_id)->pluck('part')->first()
                : 0;
            $bangumi->tags = $this->tags($bangumi);
            // json 格式化
            $bangumi->alias = $bangumi->alias === 'null' ? '' : json_decode($bangumi->alias);
            $bangumi->season = $bangumi->season === 'null' ? '' : json_decode($bangumi->season);

            return $bangumi;
        });
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id) {
            // 这里是多次 where，可以优化成 whereIn
            array_push($result, $this->item($id));
        }
        return $result;
    }

    public function timeline($year)
    {
        return Cache::remember('bangumi_news_page_' . $year, config('cache.ttl'), function () use ($year)
        {
            $begin = mktime(0, 0, 0, 1, 1, $year);
            $end = mktime(0, 0, 0, 1, 1, $year + 1);
            $ids = Bangumi::whereRaw('published_at >= ? and published_at < ?', [$begin, $end])
                ->latest('published_at')
                ->pluck('id');

            $repository = new BangumiRepository();
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
            $data = [];
            for ($i = 0; $i < $count; $i++)
            {
                $data[$i] = [
                    'date' => $keys[$i],
                    'list' => $values[$i]
                ];
            }

            return $data;
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

            return true;
        }
        else
        {
            BangumiFollow::find($followed)->delete();
            return false;
        }
    }

    public function videos($id, $season)
    {
        return Cache::remember('bangumi_'.$id, config('cache.ttl'), function () use ($id, $season)
        {
            $list = Video::where('bangumi_id', $id)->get()->toArray();

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

    public function tags($bangumi)
    {
        return Cache::remember('bangumi_'.$bangumi->id.'_tags', config('cache.ttl'), function () use ($bangumi)
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
                ->select('avatar', 'zone', 'nickname')
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
            return $ids = Post::where('bangumi_id', $id)
                ->orderBy('id', 'desc')
                ->pluck('id', 'updated_at');

        }, strtotime(date('Y-m-d')) + 86400 + rand(3600, 10800));
    }
}