<?php

namespace App\Api\V1\Controllers;

use App\Api\v1\Repositories\BangumiSeasonRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\Counter\VideoPlayCounter;
use App\Api\V1\Services\LightCoinService;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Video\BuyVideoService;
use App\Api\V1\Services\Toggle\Video\VideoLikeService;
use App\Api\V1\Services\Toggle\Video\VideoMarkService;
use App\Api\V1\Services\Toggle\Video\VideoRewardService;
use App\Api\V1\Transformers\VideoTransformer;
use App\Api\V1\Repositories\BangumiRepository;
use App\Models\Bangumi;
use App\Models\BangumiSeason;
use App\Models\Video;
use App\Services\OpenSearch\Search;
use function App\Services\Qiniu\waterImg;
use App\Services\Trial\UserIpAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $user = $this->getAuthUser();
        $userId = $user ? $user->id : 0;
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($info['bangumi_id']);
        $videoPackage = $bangumiRepository->videos($info['bangumi_id']);

        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $others_site_video = $info['other_site'];
        $bangumi = $bangumiRepository->panel($info['bangumi_id'], $userId);
        $season_id = $info['bangumi_season_id'];

        $videoTransformer = new VideoTransformer();
        $userIpAddress = new UserIpAddress();

        $searchService = new Search();
        if ($searchService->checkNeedMigrate('video', $id))
        {
            $job = (new \App\Jobs\Search\UpdateWeight('video', $id));
            dispatch($job);
        }

        $videoMarkService = new VideoMarkService();
        $videoRewardService = new VideoRewardService();
        $videoLikeService = new VideoLikeService();
        $buyVideoService = new BuyVideoService();

        $info['rewarded'] = $videoRewardService->check($userId, $id);
        $info['reward_users'] = $videoRewardService->users($id);
        $info['marked'] = $videoMarkService->check($userId, $id);
        $info['mark_users'] = $videoMarkService->users($id);
        $info['liked'] = $videoLikeService->check($userId, $id);
        $info['like_users'] = $videoLikeService->users($id);
        $info['other_site'] = $others_site_video;

        $buyed = $buyVideoService->check($userId, $season_id);
        $bangumiManager = new BangumiManager();
        $mustReward = !$bangumiManager->isALeader($userId);
        if ($userId == 5203)
        {
            $mustReward = true;
        }
        $blocked = $userIpAddress->check($userId);
        if ($user && $user->banned_to)
        {
            $blocked = true;
        }

        return $this->resOK([
            'info' => $videoTransformer->show($info),
            'bangumi' => $bangumi,
            'season_id' => $season_id,
            'list' => $videoPackage,
            'ip_blocked' => $blocked,
            'must_reward' => $buyed ? false : $mustReward,
            'buyed' => $buyed,
            'need_min_level' => 0,
            'share_data' => [
                'title' => "《{$bangumi['name']}》第{$info['part']}话",
                'desc' => $info['name'],
                'link' => $this->createShareLink('video', $id, $userId),
                'image' => "{$info['poster']}-share120jpg"
            ]
        ]);
    }


    /**
     * 承包季度视频
     *
     * @Post("/video/buy")
     *
     */
    public function buy(Request $request)
    {
        $user = $this->getAuthUser();
        $userId = $user->id;
        $buyVideoService = new BuyVideoService();
        $seasonId = $request->get('season_id');
        $buyed = $buyVideoService->check($userId, $seasonId);
        if ($buyed)
        {
            return $this->resErrRole('无需重复购买');
        }

        $lightCoinService = new LightCoinService();
        $banlance = $lightCoinService->hasMoneyCount($user);
        $PRICE = 10;
        if ($banlance < $PRICE)
        {
            return $this->resErrRole('没有足够的虚拟币');
        }

        $result = $lightCoinService->buyVideoPackage($userId, $seasonId, $PRICE);
        if (!$result)
        {
            return $this->resErrServiceUnavailable('系统维护中');
        }

        $buyVideoService->do($userId, $seasonId);

        return $this->resOK();
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
        $bangumiSeasonId = $request->get('id');
        $videoStr = BangumiSeason
            ::where('id', $bangumiSeasonId)
            ->pluck('videos')
            ->first();

        $videoIds = $videoStr ? explode(',', $videoStr) : [];
        $video = Video::withTrashed()
            ->whereIn('id', $videoIds)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($video as $row)
        {
            $row['resource'] = $row['resource'] === 'null' ? '' : json_decode($row['resource']);
        }

        return $this->resOK([
            'list' => $video,
            'total' => count($videoIds)
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
                'episode' => $request->get('episode'),
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
        $rollback = false;
        $videoIds = [];
        $bangumiSeasonId = $data[0]['bangumiSeasonId'];
        $bangumiId = $data[0]['bangumiId'];

        DB::beginTransaction();

        foreach ($data as $video)
        {
            $id = Video
                ::whereRaw('bangumi_season_id = ? and episode = ?', [$bangumiSeasonId, $video['episode']])
                ->pluck('id')
                ->first();

            if (!is_null($id))
            {
                $rollback = true;
                break;
            }

            $newId = Video::insertGetId([
                'bangumi_id' => $video['bangumiId'],
                'bangumi_season_id' => $bangumiSeasonId,
                'part' => $video['part'],
                'name' => $video['name'],
                'episode' => $video['episode'],
                'url' => $video['url'] ? $video['url'] : '',
                'resource' => $video['resource'] ? json_encode($video['resource']) : '',
                'poster' => $video['poster'],
                'user_id' => 2,
                'is_creator' => 1,
                'created_at' => $time,
                'updated_at' => $time
            ]);
            $videoIds[] = $newId;
            $videoRepository->migrateSearchIndex('C', $newId);
        }
        $oldVideos = BangumiSeason
            ::where('id', $bangumiSeasonId)
            ->pluck('videos')
            ->first();

        if ($oldVideos)
        {
            $resultVideos = $oldVideos . ',' . implode(',', $videoIds);
        }
        else
        {
            $resultVideos = implode(',', $videoIds);
        }

        $result = BangumiSeason
            ::where('id', $bangumiSeasonId)
            ->update([
                'videos' => $resultVideos
            ]);

        if (!$result)
        {
            $rollback = true;
        }

        if ($rollback)
        {
            DB::rollBack();
            return $this->resErrBad('视频上传失败');
        }
        else
        {
            Redis::DEL('bangumi_'. $bangumiId .'_videos');
            Redis::DEL('bangumi_season:bangumi:' . $bangumiId);
            DB::commit();
        }

        $job = (new \App\Jobs\Push\Baidu('bangumi/news', 'update'));
        dispatch($job);
        $job = (new \App\Jobs\Push\Baidu('bangumi/' . $bangumiId . '/video', 'update'));
        dispatch($job);

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
