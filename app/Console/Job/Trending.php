<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/15
 * Time: 上午11:20
 */

namespace App\Console\Job;

use App\Api\V1\Services\Trending\CartoonRoleTrendingService;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Api\V1\Services\Trending\QuestionTrendingService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
use App\Models\Video;
use Illuminate\Console\Command;

class Trending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Trending';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'trending job';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $postTrendingService = new PostTrendingService();
        $postTrendingService->computeHotIds();

        $imageTrendingService = new ImageTrendingService();
        $imageTrendingService->computeHotIds();

        $scoreTrendingService = new ScoreTrendingService();
        $scoreTrendingService->computeHotIds();

        $questionTrendingService = new QuestionTrendingService();
        $questionTrendingService->computeHotIds();

        $cartoonRoleTrendingService = new CartoonRoleTrendingService();
        $cartoonRoleTrendingService->computeHotIds();

        return true;
    }
}