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
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SaveIdolMarketPrice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SaveIdolMarketPrice';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'save idol market price';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $list = CartoonRole
            ::where('company_state', 1)
            ->select('id', 'market_price')
            ->get()
            ->toArray();

        $cartoonRoleRepository = new CartoonRoleRepository();
        $timeSeed = date('Y-m-d', strtotime('-1 day'));
        foreach ($list as $item)
        {
            DB
                ::table('virtual_idol_day_activity')
                ->insert([
                    'model_id' => $item['id'],
                    'value' => $item['market_price'],
                    'day' => Carbon::now()->yesterday()
                ]);

            $cacheKey = $cartoonRoleRepository->idolRealtimeMarketPrice($item['id'], $timeSeed);
            Redis::DEL($cacheKey);
        }

        return true;
    }
}