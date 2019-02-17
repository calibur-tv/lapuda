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
            ::where('created_at', '<', Carbon::now()->addDays(-7))
            ->get()
            ->toArray();

        $cartoonRoleRepository = new CartoonRoleRepository();
        $cacheKey = $cartoonRoleRepository->idolDealListCacheKey();

        foreach ($deals as $item)
        {
            if ($item['last_count'] == $item['product_count'])
            {
                VirtualIdolDeal
                    ::where('id', $item['id'])
                    ->delete();

                Redis::ZREM($cacheKey, $item['id']);
            }
        }

        return true;
    }
}