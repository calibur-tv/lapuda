<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 上午6:24
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Services\Counter\Stats\TotalCommentCount;
use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Api\V1\Services\Counter\Stats\TotalPostCount;
use App\Api\V1\Services\Counter\Stats\TotalUserCount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class StatsController extends Controller
{
    public function realtime()
    {
        $totalUserCounter = new TotalUserCount();
        $totalPostCounter = new TotalPostCount();
        $totalCommentCounter = new TotalCommentCount();
        $totalImageCounter = new TotalImageCount();

        return $this->resOK([
            'user' => $totalUserCounter->today(),
            'post' => $totalPostCounter->today(),
            'comment' => $totalCommentCounter->today(),
            'image' => $totalImageCounter->today()
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