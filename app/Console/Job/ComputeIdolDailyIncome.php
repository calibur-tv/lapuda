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
use App\Models\VirtualCoin;
use App\Models\VirtualIdolDailyIncome;
use App\Models\VirtualIdolPorduct;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ComputeIdolDailyIncome extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ComputeIdolDailyIncome';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'computed idol daily income';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $list = CartoonRole
            ::where('company_state', 1)
            ->pluck('id')
            ->toArray();

        $end = strtotime(date('Y-m-d'));
        $begin = $end - 86400;
        $begin =  Carbon::createFromTimestamp($begin)->toDateTimeString();
        $end = Carbon::createFromTimestamp($end)->toDateTimeString();

        foreach ($list as $idol_id)
        {
            $income = VirtualIdolPorduct
                ::where('updated_at', '>=', $begin)
                ->where('updated_at', '<', $end)
                ->where('result', 0)
                ->where('idol_id', $idol_id)
                ->sum('amount');

            $pay = VirtualCoin
                ::where('created_at', '>=', $begin)
                ->where('created_at', '<', $end)
                ->where('channel_type', 25)
                ->where('about_user_id', $idol_id)
                ->sum('amount');

            $pay = abs($pay);

            VirtualIdolDailyIncome
                ::create([
                    'get' => $income,
                    'set' => $pay,
                    'balance' => $this->calculate($income - $pay),
                    'day' => Carbon::now()->yesterday()
                ]);
        }

        return true;
    }

    // 四舍六入算法
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