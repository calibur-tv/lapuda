<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Job\CronFreeUser::class,
        Job\CronComputedActivity::class,
        Job\ClearSearch::class,
        Job\DayStats::class,
        Job\CronAvthumb::class,
        Job\Trending::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('CronComputedActivity')->dailyAt('00:01');
        $schedule->command('DayStats')->dailyAt('01:01');
//        $schedule->command('CronAvthumb')->hourly();
        $schedule->command('ClearSearch')->dailyAt('05:00');
        $schedule->command('CronFreeUser')->everyFiveMinutes();
        $schedule->command('Trending')->hourly();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
