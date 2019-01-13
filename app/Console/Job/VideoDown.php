<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\BangumiSeasonRepository;
use App\Models\BangumiSeason;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class VideoDown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'VideoDown';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'video down';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ids = BangumiSeason
            ::where('published_at', '<', Carbon::createFromTimestamp(strtotime('6 month ago'))->toDateTimeString()) // 6个月前发布的
            ->where('copyright_provider', 1) // bilibli
            ->where('copyright_type', 2) // 独家播放
            ->pluck('id')
            ->toArray();

        $bangumiSeasonRepository = new BangumiSeasonRepository();
        foreach ($ids as $id)
        {
            $bangumiSeasonRepository->updateVideoBySeasonId($id, true);
        }

        $ids = BangumiSeason
            ::where('copyright_provider', '<>', 1) // 不是 bilibili
            ->whereIn('copyright_type', [2, 3, 4]) // 独家播放和收费
            ->pluck('id')
            ->toArray();

        foreach ($ids as $id)
        {
            $bangumiSeasonRepository->updateVideoBySeasonId($id, true);
        }

        return true;
    }
}