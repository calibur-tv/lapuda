<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/15
 * Time: 上午11:20
 */

namespace App\Console\Job;

use App\Models\Video;
use Illuminate\Console\Command;

class CronAvthumb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CronAvthumb';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'avthumb job';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $job = (new \App\Jobs\Push\Baidu('bangumi/news', 'update'));
        dispatch($job);
        $ids = Video
            ::where('process', '')
            ->orderBy('id', 'ASC')
            ->take(100)
            ->pluck('id')
            ->toArray();

        foreach ($ids as $id)
        {
            $job = (new \App\Jobs\Qiniu\Avthumb($id));
            dispatch($job);
        }

        return true;
    }
}