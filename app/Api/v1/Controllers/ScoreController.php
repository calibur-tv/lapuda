<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午3:55
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Bangumi\BangumiScoreService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

class ScoreController extends Controller
{
    public function show()
    {

    }

    public function bangumis(Request $request)
    {
        $bangumiId = $request->get('id');
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($bangumiId);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $scoreRepository = new ScoreRepository();
        $score = $scoreRepository->bangumiScore($bangumiId);

        return $this->resOK($score);
    }

    public function users()
    {

    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bangumi_id' => 'required|integer',
            'intro' => 'required|max:120',
            'content' => 'required|string',
            'lol' => 'required|integer|min:0|max:10',
            'cry' => 'required|integer|min:0|max:10',
            'fight' => 'required|integer|min:0|max:10',
            'moe' => 'required|integer|min:0|max:10',
            'sound' => 'required|integer|min:0|max:10',
            'vision' => 'required|integer|min:0|max:10',
            'role' => 'required|integer|min:0|max:10',
            'story' => 'required|integer|min:0|max:10',
            'express' => 'required|integer|min:0|max:10',
            'style' => 'required|integer|min:0|max:10'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $bangumiId = $request->get('bangumi_id');
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->item($bangumiId);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound();
        }

        $user = $this->getAuthUser();
        $userId = $user->id;
        $bangumiScoreService = new BangumiScoreService();
        if ($bangumiScoreService->check($userId, $bangumiId))
        {
            return $this->resErrBad('不能重复评分');
        }

        $bangumiFollowService = new BangumiFollowService();
        if (!$bangumiFollowService->check($userId, $bangumiId))
        {
            // 如果没有关注，就给他关注
            $bangumiFollowService->do($userId, $bangumiId);
        }

        $lol = $request->get('lol');
        $cry = $request->get('cry');
        $fight = $request->get('fight');
        $moe = $request->get('moe');
        $sound = $request->get('sound');
        $vision = $request->get('vision');
        $role = $request->get('role');
        $story = $request->get('story');
        $express = $request->get('express');
        $style = $request->get('style');
        $total = $lol + $cry + $fight + $moe + $sound + $vision + $role + $story + $express + $style;
        $content = Purifier::clean($request->get('content'));
        $intro = $request->get('intro');
        $now = Carbon::now();

        $newId = DB::table('scores')
            ->insertGetId([
                'user_id' => $userId,
                'user_age' => 18,
                'user_sex' => $user->sex,
                'bangumi_id' => $bangumiId,
                'lol' => $lol,
                'cry' => $cry,
                'fight' => $fight,
                'moe' => $moe,
                'sound' => $sound,
                'vision' => $vision,
                'role' => $role,
                'story' => $story,
                'express' => $express,
                'style' => $style,
                'total' => $total,
                'content' => $content,
                'intro' => $intro,
                'created_at' => $now,
                'updated_at' => $now
            ]);

        $bangumiScoreService->do($userId, $bangumiId);

        $scoreRepository = new ScoreRepository();
        Redis::DEL($scoreRepository->cacheKeyBangumiScore($bangumiId));

        $scoreTrendingService = new ScoreTrendingService(0, $bangumiId);
        $scoreTrendingService->create($newId);

        // TODO：trial
        // TODO：SEO
        // TODO：SEARCH

        return $this->resOK($newId);
    }

    public function trialList()
    {

    }

    public function trialPass()
    {

    }

    public function trialBan()
    {

    }
}