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
        Job\Trending::class,
        Job\VideoDown::class,
        Job\VideoUp::class,
        Job\MigrationCoin::class,
        Job\UpdateIdolBoss::class,
        Job\SetPortecterBadge::class,
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
        $schedule->command('UpdateIdolBoss')->hourly();
        $schedule
            ->command('SetPortecterBadge')
            ->dailyAt('12:15');
//        $schedule
//            ->command('MigrationCoin')
//            ->everyMinute()
//            ->withoutOverlapping();
        $schedule->command('VideoUp')
            ->dailyAt('19:00')
            ->weekdays();
        $schedule->command('VideoDown')
            ->dailyAt('8:00')
            ->weekdays();
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
