<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\UserRepository;
use Illuminate\Console\Command;

class DayStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DayStats';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'day stats';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $repository = new UserRepository();
        $repository->statsByDate(time());

        return true;
    }
}