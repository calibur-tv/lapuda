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
        Trending\CartoonRole::class,
        Trail\BlackWords::class,
        Job\CronPush::class,
        Job\DayStats::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule
            ->command('CartoonRole')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        $schedule->command('BlackWords')->everyFiveMinutes();
        $schedule->command('CronPush')->dailyAt('23:33');
        $schedule->command('DayStats')->dailyAt('00:01');
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
