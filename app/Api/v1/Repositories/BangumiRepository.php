<?php

namespace App\Api\V1\Repositories;

use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Models\Bangumi;
use App\Models\BangumiFollow;
use App\Models\BangumiTag;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
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

            return $bangumi;
        });

        if (is_null($bangumi))
        {
            return null;
        }

        $bangumi['tags'] = $this->tags($bangumi['id']);

        return $bangumi;
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->item($id);
            if ($item) {
                $result[] = $item;
            }
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
            return date('Y', Bangumi::where('published_at', '<>', '0')->min('published_at'));
        });
    }

    public function checkUserFollowed($user_id, $bangumi_id)
    {
        if (!$user_id)
        {
            return false;
        }
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

            $job = (new \App\Jobs\Push\Baidu('bangumi/' . $bangumi_id, 'update'));
            dispatch($job);

            $job = (new \App\Jobs\Push\Baidu('user/' . User::where('id', $user_id)->pluck('zone')->first(), 'update'));
            dispatch($job);
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

        $bangumiFollowsCacheKey = 'bangumi_'.$bangumi_id.'_followersIds';
        $userFollowsCacheKey = 'user_'.$user_id.'_followBangumiIds';
        if ($result)
        {
            Redis::LPUSHX($userFollowsCacheKey, $bangumi_id);
            Redis::ZADD($bangumiFollowsCacheKey, strtotime('now'), $user_id);
        }
        else
        {
            Redis::LREM($userFollowsCacheKey, 1, $bangumi_id);
            Redis::ZREM($bangumiFollowsCacheKey, $user_id);
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
                $resetPart = isset($season->re);
                for ($i=0, $j=1; $j < count($part); $i++, $j++) {
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
            } else {
                $videos = $list;
            }

            return [
                'videos' => $videos,
                'total' => count($list)
            ];
        });
    }

    public function tags($bangumiId)
    {
        return $this->Cache('bangumi_'. $bangumiId .'_tags', function () use ($bangumiId)
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

    public function getFollowers($bangumiId, $seenIds, $take = 10)
    {
        $ids = $this->RedisSort('bangumi_'.$bangumiId.'_followersIds', function () use ($bangumiId)
        {
            return BangumiFollow::where('bangumi_id', $bangumiId)
                ->orderBy('id', 'DESC')
                ->pluck('created_at', 'user_id AS id');
        }, true, false, true);

        if (empty($ids))
        {
            return [];
        }

        foreach ($ids as $key => $val)
        {
            if (in_array($key, $seenIds))
            {
                unset($ids[$key]);
            }
        }

        if (empty($ids))
        {
            return [];
        }

        $ids = array_slice($ids, 0, $take, true);

        if (empty($ids))
        {
            return [];
        }

        $repository = new UserRepository();
        $transformer = new UserTransformer();
        $users = [];
        $i = 0;
        foreach ($ids as $id => $score)
        {
            $users[] = $transformer->item($repository->item($id));
            $users[$i]['score'] = (int)$score;
            $i++;
        }

        return $users;
    }

    public function getPostIds($id, $type)
    {
        $postRepository = new PostRepository();
        $cacheKey = $postRepository->bangumiListCacheKey($id, $type);

        return $this->RedisSort($cacheKey, function () use ($id)
        {
            return Post::where('bangumi_id', $id)
                ->orderBy('id', 'DESC')
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
            return [
                'list' => $transformer->category($this->list($ids)),
                'total' => count($data)
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
            $bangumi['followed'] = $bangumiFollowService->check($userId, $bangumiId);
        }
        else
        {
            $bangumi['followed'] = false;
        }

        $transformer = new BangumiTransformer();
        return $transformer->panel($bangumi);
    }
}