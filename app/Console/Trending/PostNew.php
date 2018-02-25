<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Trending;

use App\Api\V1\Repositories\PostRepository;
use Illuminate\Console\Command;

class PostNew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PostNew';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'compute post new list';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $repository = new PostRepository();
        $repository->getNewIds(true);
    }
}