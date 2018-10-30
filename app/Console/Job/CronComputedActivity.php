<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Api\V1\Services\Activity\BangumiActivity;
use App\Api\V1\Services\Activity\UserActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CronComputedActivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CronComputedActivity';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'cron computed activity';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $userActivityService = new UserActivity();

        DB
            ::table('users')
            ->orderBy('id')
            ->pluck('id')
            ->chunk(100, function($ids) use ($userActivityService)
            {
                foreach ($ids as $id)
                {
                    $userActivityService->migrate($id);
                }
            });

        $bangumiActivityService = new BangumiActivity();

        DB
            ::table('bangumis')
            ->orderBy('id')
            ->pluck('id')
            ->chunk(100, function($ids) use ($bangumiActivityService)
            {
                foreach ($ids as $id)
                {
                    $bangumiActivityService->migrate($id);
                }
            });

        return true;
    }
}