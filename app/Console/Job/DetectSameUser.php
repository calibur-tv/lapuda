<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/15
 * Time: 上午11:20
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\UserRepository;
use App\Models\User;
use App\Services\Trial\UserIpAddress;
use Illuminate\Console\Command;

class DetectSameUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DetectSameUser';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'detect same user';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $userIpAddress = new UserIpAddress();
        User::orderBy('id', 'ASC')
            ->select('id')
            ->whereNull('banned_to')
            ->chunk(100, function($list) use ($userIpAddress)
            {
                foreach ($list as $item)
                {
                    $userIds = $userIpAddress->getSameUserById($item->id);
                    User::where('id', $item->id)
                        ->update([
                            'migration_count' => count($userIds)
                        ]);
                }
            });

        return true;
    }
}