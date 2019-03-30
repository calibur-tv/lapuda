<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Models\VirtualIdolDeal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DeleteIdolDeal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DeleteIdolDeal';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'delete idol deal';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $deals = VirtualIdolDeal
            ::where('updated_at', '<', Carbon::now()->addDays(-7))
            ->get()
            ->toArray();

        $cartoonRoleRepository = new CartoonRoleRepository();
        $cacheKey = $cartoonRoleRepository->idolDealListCacheKey();

        foreach ($deals as $item)
        {
            if (
                $item['last_count'] == $item['product_count'] ||
                $this->calculate($item['product_price'] * $item['last_count']) < 0.01
            )
            {
                VirtualIdolDeal
                    ::where('id', $item['id'])
                    ->delete();

                Redis::ZREM($cacheKey, $item['id']);

                $userCacheKey = $cartoonRoleRepository->user_deal_list_cache_key($item['user_id']);
                Redis::ZREM($userCacheKey, $item['id']);
            }
        }

        return true;
    }

    protected function calculate($num, $precision = 2)
    {
        $pow = pow(10, $precision);
        if (
            (floor($num * $pow * 10) % 5 == 0) &&
            (floor($num * $pow * 10) == $num * $pow * 10) &&
            (floor($num * $pow) % 2 == 0)
        )
        {
            return floor($num * $pow) / $pow;
        } else {
            return round($num, $precision);
        }
    }
}