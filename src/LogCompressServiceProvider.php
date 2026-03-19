<?php

namespace vocweb\LaravelLogCompress;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use vocweb\LaravelLogCompress\Commands\CompressLogsCommand;

class LogCompressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/log-compress.php', 'log-compress');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/log-compress.php' => config_path('log-compress.php'),
        ], 'log-compress-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CompressLogsCommand::class,
            ]);
        }

        $this->registerSchedule();
    }

    /**
     * Register the scheduled command if auto_schedule is enabled.
     */
    protected function registerSchedule(): void
    {
        if (! $this->app['config']->get('log-compress.auto_schedule', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $time = $this->app['config']->get('log-compress.schedule_time', '00:00');
            $schedule->command('log:compress')->dailyAt($time);
        });
    }
}
