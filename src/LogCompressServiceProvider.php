<?php

namespace vocweb\LaravelLogCompress;

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
    }
}
