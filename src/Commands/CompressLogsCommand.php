<?php

namespace vocweb\LaravelLogCompress\Commands;

use Illuminate\Console\Command;
use vocweb\LaravelLogCompress\Services\LogCompressorService;

class CompressLogsCommand extends Command
{
    protected $signature = 'log:compress
                            {--password= : Password for encrypting compressed files (overrides config)}';

    protected $description = 'Compress daily rotated log files to tar.gz archives';

    public function handle(LogCompressorService $service): int
    {
        $password = $this->option('password');

        $this->info('Scanning for daily log files to compress...');

        $result = $service->run($password);

        if ($result['compressed'] > 0) {
            $this->info("Compressed {$result['compressed']} log file(s).");
        } else {
            $this->info('No log files found to compress.');
        }

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return count($result['errors']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
