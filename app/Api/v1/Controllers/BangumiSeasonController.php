<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\v1\Repositories\BangumiSeasonRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Models\Bangumi;
use App\Models\BangumiSeason;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BangumiSeasonController extends Controller
{
    public function released()
    {
        $data = Cache::remember('bangumi_release_list', 60, function ()
        {
            $list = BangumiSeason::where('released_at', '<>', '')->orderBy('released_time', 'DESC')->get()->toArray();


            $result = [
                [], [], [], [], [], [], [], []
            ];

            $bangumiRepository = new BangumiRepository();

            foreach ($list as $item) {
                $bangumi = $bangumiRepository->item($item['bangumi_id']);

                $item['bangumi_name'] = $bangumi['name'] ?? '';
                $item['update'] = time() - $item['released_time'] < 604800;

                $videos = json_decode($item['videos'], true);

                if (empty($videos)) {
                    $item['released_video_id'] = 0;
                    $item['released_part'] = 0;
                } else {
                    $videoRepository = new VideoRepository();
                    $video = $videoRepository->item(last($videos));
                    $item['released_video_id'] = $video['id'];
                    $item['released_part'] = $video['episode'];
                }

                $releaseAt = json_decode($item['released_at'], true);
                if (is_null($releaseAt)) {
                    continue;
                }

                foreach ($releaseAt as $day) {
                    $result[$day][] = $item;
                }
                $result[0][] = $item;
            }

            $bangumiTransformer = new BangumiTransformer();
            foreach ($result as $i => $arr) {
                $result[$i] = $bangumiTransformer->released($arr);
            }

            return $result;
        });

        return $this->resOK($data);
    }

    public function bangumiVideos($id)
    {
        $repository = new BangumiRepository();
        $bangumi = $repository->item($id);

        if (is_null($bangumi))
        {
            return $this->resErrNotFound('没有找到番剧');
        }

        $videoRepository = new VideoRepository();
        $bangumiSeasonRepository = new BangumiSeasonRepository();
        $seasons = $bangumiSeasonRepository->listByBangumiId($bangumi['id']);
        array_walk($seasons, function (&$season) use ($videoRepository) {
            $videoIds = json_decode($season['videos'], true);
            $season = [
                'name' => $season['name'],
                'time' => date('Y.m', $season['published_at']),
            ];
            $videos = $videoRepository->list($videoIds);
            foreach ($videos as $video) {
                $season['videos'][] = [
                    'id' => $video['id'],
                    'name' => $video['name'],
                    'poster' => $video['poster'],
                    'episode' => $video['episode'],
                ];
            }
        });

        return $this->resOK(['seasons' => $seasons]);
    }

    public function create(Request $request)
    {
        $time = Carbon::now();
        $bangumiId = BangumiSeason::insertGetId([
            'bangumi_id' => $request->get('bangumi_id'),
            'name' => $request->get('name'),
            'rank' => $request->get('rank'),
            'summary' => $request->get('summary'),
            'avatar' => $request->get('avatar'),
            'published_at' => $request->get('published_at') ?: 0,
            'released_at' => $request->get('released_at'),
            'videos' => $request->get('videos') ? json_encode($request->get('videos')) : 'null',
            'other_site_video' => $request->get('other_site_video'),
            'end' => $request->get('end'),
            'created_at' => $time,
            'updated_at' => $time
        ]);

        Redis::DEL('bangumi_release_list');
        Redis::DEL('bangumi_season:bangumi:' . $bangumiId);

        return $this->resCreated($bangumiId);
    }

    public function edit(Request $request)
    {
        $bangumiSeasonId = $request->get('id');
        $bangumiId = $request->get('bangumi_id');

        $arr = [
            'bangumi_id' => $request->get('bangumi_id'),
            'name' => $request->get('name'),
            'rank' => $request->get('rank'),
            'summary' => $request->get('summary'),
            'avatar' => $request->get('avatar'),
            'published_at' => $request->get('published_at') ?: 0,
            'released_at' => $request->get('released_at'),
            'videos' => $request->get('videos') ? json_encode($request->get('videos')) : 'null',
            'other_site_video' => $request->get('other_site_video'),
            'end' => $request->get('end'),
            'updated_at' => Carbon::now(),
        ];

        $result = BangumiSeason::where('id', $bangumiSeasonId)->update($arr);
        if ($result === false) {
            return $this->resErrBad('更新失败');
        }

        Redis::DEL('bangumi_season:bangumi:'.$bangumiId);
        Redis::DEL('bangumi_'.$bangumiId.'_videos');

        return $this->resNoContent();
    }

    public function list(Request $request)
    {
        $bangumiId = $request->get('bangumi_id');

        $bangumiSeasonRepository = new BangumiSeasonRepository();
        $seasons = $bangumiSeasonRepository->listByBangumiId($bangumiId);

        return $this->resOK([
            'seasons' => $seasons,
        ]);
    }
}
