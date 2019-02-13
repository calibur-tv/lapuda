<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/15
 * Time: 上午11:20
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Models\CartoonRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ComputeIdolMarketPrice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ComputeIdolMarketPrice';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'computed idol market price';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cartoonRoleRepository = new CartoonRoleRepository();
        $list = CartoonRole
            ::where('company_state', 1)
            ->select('id', 'market_price')
            ->get()
            ->toArray();

        foreach ($list as $item)
        {
            $cacheKey = $cartoonRoleRepository->idolRealtimeMarketPrice($item['id']);
            Redis::ZADD($cacheKey, strtotime('now'), $item['market_price']);
        }

        return true;
    }
}