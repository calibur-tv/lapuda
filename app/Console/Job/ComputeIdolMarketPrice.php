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
            ::where('fans_count', '>', 0)
            ->select('id', 'market_price')
            ->get()
            ->toArray();

        $time = strtotime('now');
        foreach ($list as $item)
        {
            $cacheKey = $cartoonRoleRepository->idolRealtimeMarketPrice($item['id']);
            Redis::ZADD($cacheKey, $time, $item['market_price']);
        }

        foreach ($list as $item)
        {
            $cacheKey = $cartoonRoleRepository->idol24HourMarketPrice($item['id']);
            Redis::LPUSH($cacheKey, "{$time}-{$item['market_price']}");
            Redis::LTRIM($cacheKey, 0, 288);
        }

        return true;
    }
}