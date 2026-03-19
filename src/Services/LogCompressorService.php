<?php

namespace vocweb\LaravelLogCompress\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class LogCompressorService
{
    /**
     * Get all daily log channels from the application's logging config.
     *
     * @return array<string, array> Channel name => channel config
     */
    public function getDailyLogChannels(): array
    {
        $channels = Config::get('logging.channels', []);

        return array_filter($channels, function (array $channel) {
            return ($channel['driver'] ?? null) === 'daily';
        });
    }

    /**
     * Find log files eligible for compression in a daily channel.
     *
     * @param string $channelPath The 'path' value from channel config (e.g., storage_path('logs/laravel.log'))
     * @param int $olderThanDays Only compress files older than this many days
     * @return string[] Absolute paths of compressable log files
     */
    public function findCompressableLogFiles(string $channelPath, int $olderThanDays = 1): array
    {
        $directory = dirname($channelPath);
        $baseName = pathinfo($channelPath, PATHINFO_FILENAME);

        if (! is_dir($directory)) {
            return [];
        }

        // Daily logs follow pattern: {baseName}-YYYY-MM-DD.log
        $pattern = $directory . '/' . $baseName . '-????-??-??.log';
        $files = glob($pattern) ?: [];

        $cutoffDate = now()->subDays($olderThanDays)->startOfDay();

        return array_values(array_filter($files, function (string $file) use ($cutoffDate) {
            // Extract date from filename (last 10 chars before .log)
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $dateStr = substr($filename, -10);

            $fileDate = \Carbon\Carbon::createFromFormat('Y-m-d', $dateStr);

            // createFromFormat returns false on invalid date strings
            if ($fileDate === false || $fileDate->greaterThanOrEqualTo($cutoffDate)) {
                return false;
            }

            // Skip if already compressed
            if (file_exists($file . '.tar.gz')) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Compress a single log file to tar.gz, optionally with password encryption.
     * Password is passed via environment variable to avoid exposure in process list.
     *
     * @param string $filePath Absolute path to the log file
     * @param string|null $password Password for encryption (null = no encryption)
     * @return bool True on success
     * @throws RuntimeException If compression fails or file path is invalid
     */
    public function compressFile(string $filePath, ?string $password = null): bool
    {
        // Validate file exists and resolve to real path to prevent traversal
        $realPath = realpath($filePath);
        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException("Log file does not exist: {$filePath}");
        }

        $directory = dirname($realPath);
        $fileName = basename($realPath);
        $outputPath = $realPath . '.tar.gz';

        if ($password !== null && $password !== '') {
            // Encrypted: tar | openssl pipeline; password via env var (not visible in ps)
            $command = sprintf(
                'tar czf - -C %s %s | openssl enc -aes-256-cbc -pbkdf2 -iter 10000 -pass env:LOG_COMPRESS_PWD -out %s',
                escapeshellarg($directory),
                escapeshellarg($fileName),
                escapeshellarg($outputPath)
            );

            $result = Process::env(['LOG_COMPRESS_PWD' => $password])->run($command);
        } else {
            // Plain tar.gz
            $command = sprintf(
                'tar czf %s -C %s %s',
                escapeshellarg($outputPath),
                escapeshellarg($directory),
                escapeshellarg($fileName)
            );

            $result = Process::run($command);
        }

        if (! $result->successful()) {
            // Clean up partial output on failure
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
            throw new RuntimeException(
                "Failed to compress {$filePath}: " . $result->errorOutput()
            );
        }

        // Verify output file was created and is non-empty
        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
            throw new RuntimeException("Compressed file was not created: {$outputPath}");
        }

        return true;
    }

    /**
     * Delete the original log file after successful compression.
     *
     * @throws RuntimeException If deletion fails
     */
    public function deleteOriginal(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            return false;
        }

        if (! unlink($filePath)) {
            throw new RuntimeException("Failed to delete original log file: {$filePath}");
        }

        return true;
    }

    /**
     * Run the full compression workflow.
     *
     * @param string|null $password Override password (null = use config)
     * @return array{compressed: int, errors: string[]}
     */
    public function run(?string $password = null): array
    {
        $password = $password ?? Config::get('log-compress.password');
        $deleteAfter = Config::get('log-compress.delete_after_compress', true);
        $olderThanDays = Config::get('log-compress.older_than_days', 1);

        $channels = $this->getDailyLogChannels();
        $compressed = 0;
        $errors = [];

        foreach ($channels as $name => $channel) {
            $channelPath = $channel['path'] ?? null;

            if ($channelPath === null) {
                continue;
            }

            $files = $this->findCompressableLogFiles($channelPath, $olderThanDays);

            foreach ($files as $file) {
                try {
                    $this->compressFile($file, $password);
                    $compressed++;

                    if ($deleteAfter) {
                        $this->deleteOriginal($file);
                    }
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        return [
            'compressed' => $compressed,
            'errors' => $errors,
        ];
    }
}
