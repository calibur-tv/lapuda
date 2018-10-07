<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\Counter\VideoPlayCounter;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Video\VideoMarkService;
use App\Api\V1\Services\Toggle\Video\VideoRewardService;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\VideoTransformer;
use App\Api\V1\Repositories\BangumiRepository;
use App\Models\Video;
use App\Services\OpenSearch\Search;
use App\Services\Trial\UserIpAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

/**
 * @Resource("视频相关接口")
 */
class VideoController extends Controller
{
    /**
     * 获取视频资源
     *
     * @Get("/video/${videoId}/show")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"info": "视频对象", "bangumi": "番剧信息", "list": {"total": "视频总数", "repeat": "是否重排", "videos": "视频列表"}}}),
     *      @Response(404, body={"code": 40401, "message": "不存在的视频资源"})
     * })
     */
    public function show(Request $request, $id)
    {
        $videoRepository = new VideoRepository();
        $isPC = $request->get("refer") ?: false;
        $info = $videoRepository->item($id, false, $isPC);

        if (is_null($info))
        {
            return $this->resErrNotFound('不存在的视频资源');
        }

        $userId = $this->getAuthUserId();
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($info['bangumi_id']);

        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $season = json_decode($bangumi['season']);
        $list = $bangumiRepository->videos($bangumi['id'], $season);

        $bangumiFollowService = new BangumiFollowService();
        $bangumi['followed'] = $bangumiFollowService->check($userId, $info['bangumi_id']);

        $videoTransformer = new VideoTransformer();
        $bangumiTransformer = new BangumiTransformer();
        $userIpAddress = new UserIpAddress();

        $searchService = new Search();
        if ($searchService->checkNeedMigrate('video', $id))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('video', $id));
            dispatch($job);
        }

        $videoMarkService = new VideoMarkService();
        $videoRewardService = new VideoRewardService();

        $info['rewarded'] = $videoRewardService->check($userId, $id);
        $info['reward_users'] = $videoRewardService->users($id);
        $info['marked'] = $videoMarkService->check($userId, $id);
        $info['mark_users'] = $videoMarkService->users($id);

        $mustReward = $bangumi['released_video_id'] == $id && $bangumi['end'] == 0;

        return $this->resOK([
            'info' => $videoTransformer->show($info),
            'bangumi' => $bangumiTransformer->video($bangumi),
            'season' => $season,
            'list' => $list,
            'ip_blocked' => $userIpAddress->check($userId),
            'must_reward' => $mustReward,
            'need_min_level' => $mustReward ? 0 : 3
        ]);
    }

    /**
     * 记录视频播放信息
     *
     * @Get("/video/${videoId}/playing")
     *
     * @Request(headers={"Authorization": "Bearer JWT-Token"}),
     */
    public function playing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $videoPlayCounter = new VideoPlayCounter();
        $videoPlayCounter->add($request->get('id'));

        return $this->resNoContent();
    }

    // 后台获取番剧的视频列表
    public function bangumis(Request $request)
    {
        $bangumiId = $request->get('id');
        $curPage = $request->get('cur_page') ?: 0;
        $toPage = $request->get('to_page') ?: 1;
        $take = $request->get('take') ?: 10;

        $total = Video::withTrashed()
            ->where('bangumi_id', $bangumiId)
            ->count();
        $video = Video::withTrashed()
            ->where('bangumi_id', $bangumiId)
            ->orderBy('id', 'DESC')
            ->take(($toPage - $curPage) * $take)
            ->skip($curPage * $take)
            ->get();

        foreach ($video as $row)
        {
            $row['resource'] = $row['resource'] === 'null' ? '' : json_decode($row['resource']);
        }

        return $this->resOK([
            'list' => $video,
            'total' => $total
        ]);
    }

    // 后台编辑视频
    public function edit(Request $request)
    {
        $videoId = $request->get('id');
        $name = $request->get('name');
        Video::withTrashed()->where('id', $videoId)
            ->update([
                'name' => $name,
                'bangumi_id' => $request->get('bangumi_id'),
                'part' => $request->get('part'),
                'poster' => $request->get('poster'),
                'url' => $request->get('url') ? $request->get('url') : '',
                'resource' => json_encode($request->get('resource'))
            ]);

        Redis::DEL('video_' . $videoId);
        Redis::DEL('bangumi_' . $request->get('bangumi_id') . '_videos');

        $videoRepository = new VideoRepository();
        $videoRepository->migrateSearchIndex('U', $videoId);

        return $this->resNoContent();
    }

    // 后台批量保存视频
    public function save(Request $request)
    {
        $data = $request->all();
        $time = Carbon::now();
        $videoRepository = new VideoRepository();

        foreach ($data as $video)
        {
            $id = Video::whereRaw('bangumi_id = ? and part = ?', [$video['bangumiId'], $video['part']])->pluck('id')->first();
            if (is_null($id))
            {
                $newId = Video::insertGetId([
                    'bangumi_id' => $video['bangumiId'],
                    'part' => $video['part'],
                    'name' => $video['name'],
                    'url' => $video['url'] ? $video['url'] : '',
                    'resource' => $video['resource'] ? json_encode($video['resource']) : '',
                    'poster' => $video['poster'],
                    'user_id' => 2,
                    'is_creator' => 1,
                    'created_at' => $time,
                    'updated_at' => $time
                ]);

                $videoRepository->migrateSearchIndex('C', $newId);
            }
            else
            {
                Video::where('id', $id)->update([
                    'bangumi_id' => $video['bangumiId'],
                    'part' => $video['part'],
                    'name' => $video['name'],
                    'url' => $video['url'] ? $video['url'] : '',
                    'resource' => $video['resource'] ? json_encode($video['resource']) : '',
                    'poster' => $video['poster'],
                    'updated_at' => $time
                ]);

                Redis::DEL('video_' . $id);

                $videoRepository->migrateSearchIndex('U', $id);
            }
            Redis::DEL('bangumi_'.$video['bangumiId'].'_videos');
        }

        return $this->resNoContent();
    }

    // 后台删除视频
    public function delete(Request $request)
    {
        $videoId = $request->get('id');
        $videoRepository = new VideoRepository();
        $video = $videoRepository->item($videoId, true);

        if ($video['deleted_at'])
        {
            Video::withTrashed()->where('id', $videoId)->restore();

            $videoRepository->migrateSearchIndex('C', $videoId);
        }
        else
        {
            Video::withTrashed()->where('id', $videoId)->delete();

            $job = (new \App\Jobs\Search\Index('D', 'video', $videoId));
            dispatch($job);
        }

        Redis::DEL('video_' . $videoId);
        Redis::DEL('bangumi_' . $video['bangumi_id'] . '_videos');

        return $this->resNoContent();
    }

    // 后台查看播放排行榜
    public function playTrending(Request $request)
    {
        $curPage = $request->get('cur_page') ?: 0;
        $toPage = $request->get('to_page') ?: 1;
        $take = $request->get('take') ?: 10;

        $list = Video::orderBy('count_played', 'DESC')
            ->select('name', 'id', 'bangumi_id', 'count_played')
            ->take(($toPage - $curPage) * $take)
            ->skip($curPage * $take)
            ->get();

        return $this->resOK([
            'list' => $list,
            'total' => Video::count()
        ]);
    }
}
