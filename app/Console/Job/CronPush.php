<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use Illuminate\Console\Command;

class CronPush extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CronPush';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'push job';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $job = (new \App\Jobs\Push\Baidu('bangumi/news', 'update'));
        dispatch($job);
    }
}