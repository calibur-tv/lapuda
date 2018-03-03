<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Trending;

use App\Api\V1\Repositories\CartoonRoleRepository;
use Illuminate\Console\Command;

class CartoonRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CartoonRole';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'compute cartoon role hot list';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $repository = new CartoonRoleRepository();
        $repository->trendingIds(true);
    }
}