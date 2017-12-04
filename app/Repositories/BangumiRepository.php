<?php

namespace App\Repositories;

use App\Models\Bangumi;
use App\Models\BangumiFollow;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;

class BangumiRepository
{
    public function item($id)
    {
        return Cache::remember('bangumi_'.$id.'_info', config('cache.ttl'), function () use ($id)
        {
            $bangumi = Bangumi::find($id);
            // 番剧可能不存在
            if (is_null($bangumi)) {
                return null;
            }
            // 这里可以使用 LEFT-JOIN 语句优化
            $bangumi->released_part = $bangumi->released_video_id
                ? Video::find($bangumi->released_video_id)->pluck('part')
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

    public function checkUserFollowed($user_id, $bangumi_id)
    {
        return BangumiFollow::whereRaw('user_id = ? and bangumi_id = ?', [$user_id, $bangumi_id])->count();
    }

    public function toggleFollow($user_id, $bangumi_id)
    {
        $followed = BangumiFollow::whereRaw('user_id = ? and bangumi_id = ?', [$user_id, $bangumi_id])
            ->select('id')
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
            BangumiFollow::find($followed->id)->delete();
            return false;
        }
    }

    public function videos($bangumi)
    {
        return Cache::remember('bangumi_'.$bangumi->id.'_video', config('cache.ttl'), function () use ($bangumi)
        {
            $season = $bangumi['season'] === 'null' ? '' : $bangumi['season'];

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
}