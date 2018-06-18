<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Trending;

use App\Api\V1\Services\Trending\PostTrendingService;
use Illuminate\Console\Command;

class Post extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PostTrending';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'compute post trending id list';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $postTrendingService = new PostTrendingService();
        $postTrendingService->deleteIdsCache();

        return true;
    }
}