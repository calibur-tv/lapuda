<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CronFreeUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CronFreeUser';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'free blocked user';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ids = User
            ::where('banned_to', '<', Carbon::now())
            ->pluck('id')
            ->toArray();

        User::whereIn('id', $ids)
            ->update([
                'banned_to' => null
            ]);

        Redis::pipeline(function ($pipe) use ($ids)
        {
            foreach ($ids as $id)
            {
                $pipe->DEL('user_' . $id);
            }
        });

        return true;
    }
}