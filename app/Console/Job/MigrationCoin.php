<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/15
 * Time: 上午11:20
 */

namespace App\Console\Job;

use App\Api\V1\Services\LightCoinService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
        $lastMigrationId = Redis::GET('last_migration_id');
        if (!$lastMigrationId)
        {
            $lastMigrationId = 0;
        }
        $lightCoinService = new LightCoinService();
        $coinIds = UserCoin
            ::withTrashed()
            ->where('id', '>', $lastMigrationId)
            ->take(10000)
            ->orderBy('id', 'ASC')
            ->pluck('id')
            ->toArray();

        foreach ($coinIds as $cid)
        {
            Log::info('migration coin id：' . $cid);
            $result = $lightCoinService->migration($cid);
            $lastMigrationId = $cid;
            if (!$result)
            {
                Redis::LPUSH('migration_coin_failed_id', $cid);
            }
        }

        Redis::SET('last_migration_id', $lastMigrationId);

        return true;
    }
}