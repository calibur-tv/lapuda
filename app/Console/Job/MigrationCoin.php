<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/15
 * Time: 上午11:20
 */

namespace App\Console\Job;

use App\Api\V1\Services\LightCoinService;
use App\Models\UserCoin;
use Illuminate\Console\Command;

class MigrationCoin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'MigrationCoin';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'migration coin';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $lightCoinService = new LightCoinService();
        $coinIds = UserCoin
            ::withTrashed()
            ->where('migration_state', 0)
            ->take(30)
            ->orderBy('id', 'ASC')
            ->pluck('id')
            ->toArray();

        foreach ($coinIds as $cid)
        {
            $result = $lightCoinService->migration($cid);
            UserCoin
                ::where('id', $cid)
                ->update([
                    'migration_state' => $result ? 1 : 2
                ]);
        }

        return true;
    }
}