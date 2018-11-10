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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            ->where('exp', '<>', 0)
            ->select('id')
            ->chunk(100, function($users) use ($userActivityService)
            {
                foreach ($users as $user)
                {
                    $userActivityService->migrate($user->id);
                }
            });

        $bangumiActivityService = new BangumiActivity();

        DB
            ::table('bangumis')
            ->orderBy('id')
            ->select('id')
            ->chunk(100, function($users) use ($bangumiActivityService)
            {
                foreach ($users as $user)
                {
                    $bangumiActivityService->migrate($user->id);
                }
            });

        return true;
    }
}