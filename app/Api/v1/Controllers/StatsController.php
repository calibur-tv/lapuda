<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 上午6:24
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Services\Counter\Stats\TotalAnswerCount;
use App\Api\V1\Services\Counter\Stats\TotalCommentCount;
use App\Api\V1\Services\Counter\Stats\TotalImageAlbumCount;
use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Api\V1\Services\Counter\Stats\TotalPostCount;
use App\Api\V1\Services\Counter\Stats\TotalQuestionCount;
use App\Api\V1\Services\Counter\Stats\TotalScoreCount;
use App\Api\V1\Services\Counter\Stats\TotalUserCount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class StatsController extends Controller
{
    public function realtime()
    {
        $totalUserCount = new TotalUserCount();
        $totalPostCount = new TotalPostCount();
        $totalCommentCount = new TotalCommentCount();
        $totalImageCount = new TotalImageCount();
        $totalScoreCount = new TotalScoreCount();
        $totalQuestionCount = new TotalQuestionCount();
        $totalAnswerCount = new TotalAnswerCount();
        $totalAlbumCount = new TotalImageAlbumCount();

        return $this->resOK([
            'user' => $totalUserCount->today(),
            'post' => $totalPostCount->today(),
            'comment' => $totalCommentCount->today(),
            'image' => $totalImageCount->today(),
            'image_album' => $totalAlbumCount->today(),
            'score' => $totalScoreCount->today(),
            'question' => $totalQuestionCount->today(),
            'answer' => $totalAnswerCount->today()
        ]);
    }

    public function timeline(Request $request)
    {
        $today = strtotime(date('Y-m-d', time()));
        $days = $request->get('days') ?: 30;

        $data = Cache::remember('admin-index-data-' . $today, 720, function () use ($today, $days)
        {
            return DB::table('day_stats')
                ->where('day', '>', $today - 86400 * $days)
                ->get();
        });

        return $this->resOK($data);
    }
}