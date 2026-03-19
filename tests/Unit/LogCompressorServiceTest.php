<?php

namespace vocweb\LaravelLogCompress\Tests\Unit;

use Illuminate\Support\Facades\Config;
use vocweb\LaravelLogCompress\Services\LogCompressorService;
use vocweb\LaravelLogCompress\Tests\TestCase;

class LogCompressorServiceTest extends TestCase
{
    private LogCompressorService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LogCompressorService();
        $this->tempDir = sys_get_temp_dir() . '/laravel-log-compress-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $files = glob($this->tempDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Create a fake log file with a date suffix.
     */
    private function createLogFile(string $baseName, string $date, string $content = 'test log'): string
    {
        $path = $this->tempDir . "/{$baseName}-{$date}.log";
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Set up a daily logging channel in config.
     */
    private function configureDailyChannel(string $name = 'daily', string $baseName = 'laravel'): void
    {
        Config::set("logging.channels.{$name}", [
            'driver' => 'daily',
            'path' => $this->tempDir . "/{$baseName}.log",
            'level' => 'debug',
            'days' => 14,
        ]);
    }

    public function test_finds_daily_log_channels(): void
    {
        Config::set('logging.channels', [
            'daily' => [
                'driver' => 'daily',
                'path' => storage_path('logs/laravel.log'),
            ],
            'single' => [
                'driver' => 'single',
                'path' => storage_path('logs/laravel.log'),
            ],
            'stderr' => [
                'driver' => 'monolog',
            ],
        ]);

        $channels = $this->service->getDailyLogChannels();

        $this->assertCount(1, $channels);
        $this->assertArrayHasKey('daily', $channels);
        $this->assertEquals('daily', $channels['daily']['driver']);
    }

    public function test_skips_single_channel_logs(): void
    {
        Config::set('logging.channels', [
            'single' => [
                'driver' => 'single',
                'path' => storage_path('logs/laravel.log'),
            ],
        ]);

        $channels = $this->service->getDailyLogChannels();

        $this->assertEmpty($channels);
    }

    public function test_finds_compressable_log_files(): void
    {
        $channelPath = $this->tempDir . '/laravel.log';

        // Create log files for past dates
        $this->createLogFile('laravel', now()->subDays(3)->format('Y-m-d'));
        $this->createLogFile('laravel', now()->subDays(5)->format('Y-m-d'));

        $files = $this->service->findCompressableLogFiles($channelPath, 1);

        $this->assertCount(2, $files);
    }

    public function test_skips_today_log_file(): void
    {
        $channelPath = $this->tempDir . '/laravel.log';

        // Create today's log and yesterday's log
        $this->createLogFile('laravel', now()->format('Y-m-d'));
        $this->createLogFile('laravel', now()->subDays(2)->format('Y-m-d'));

        $files = $this->service->findCompressableLogFiles($channelPath, 1);

        $this->assertCount(1, $files);
        $this->assertStringContains(now()->subDays(2)->format('Y-m-d'), $files[0]);
    }

    public function test_skips_already_compressed_files(): void
    {
        $channelPath = $this->tempDir . '/laravel.log';
        $date = now()->subDays(3)->format('Y-m-d');

        $logFile = $this->createLogFile('laravel', $date);
        // Create a fake .tar.gz to simulate already compressed
        file_put_contents($logFile . '.tar.gz', 'fake archive');

        $files = $this->service->findCompressableLogFiles($channelPath, 1);

        $this->assertEmpty($files);
    }

    public function test_compresses_log_file_without_password(): void
    {
        $logFile = $this->createLogFile('laravel', '2026-01-01', 'test log content');

        $result = $this->service->compressFile($logFile);

        $this->assertTrue($result);
        $this->assertFileExists($logFile . '.tar.gz');
    }

    public function test_compresses_log_file_with_password(): void
    {
        $logFile = $this->createLogFile('laravel', '2026-01-02', 'secret log content');

        $result = $this->service->compressFile($logFile, 'mypassword123');

        $this->assertTrue($result);
        $this->assertFileExists($logFile . '.tar.gz');

        // Encrypted file should not be a valid gzip (it's AES encrypted)
        $content = file_get_contents($logFile . '.tar.gz');
        // gzip magic number is 1f 8b — encrypted file won't have it
        $this->assertNotEquals("\x1f\x8b", substr($content, 0, 2));
    }

    public function test_deletes_original_after_compression(): void
    {
        $logFile = $this->createLogFile('laravel', '2026-01-03', 'delete me');

        $this->service->compressFile($logFile);
        $deleted = $this->service->deleteOriginal($logFile);

        $this->assertTrue($deleted);
        $this->assertFileDoesNotExist($logFile);
        $this->assertFileExists($logFile . '.tar.gz');
    }

    public function test_handles_older_than_days_config(): void
    {
        $channelPath = $this->tempDir . '/laravel.log';

        // Create files at various ages
        $this->createLogFile('laravel', now()->subDays(1)->format('Y-m-d'));
        $this->createLogFile('laravel', now()->subDays(3)->format('Y-m-d'));
        $this->createLogFile('laravel', now()->subDays(7)->format('Y-m-d'));

        // Only files older than 5 days
        $files = $this->service->findCompressableLogFiles($channelPath, 5);

        $this->assertCount(1, $files);
        $this->assertStringContains(now()->subDays(7)->format('Y-m-d'), $files[0]);
    }

    public function test_run_compresses_daily_channel_logs(): void
    {
        $this->configureDailyChannel();

        // Create old log files
        $date = now()->subDays(3)->format('Y-m-d');
        $logFile = $this->createLogFile('laravel', $date);

        Config::set('log-compress.password', null);
        Config::set('log-compress.delete_after_compress', true);
        Config::set('log-compress.older_than_days', 1);

        $result = $this->service->run();

        $this->assertEquals(1, $result['compressed']);
        $this->assertEmpty($result['errors']);
        $this->assertFileDoesNotExist($logFile);
        $this->assertFileExists($logFile . '.tar.gz');
    }

    public function test_returns_empty_for_nonexistent_directory(): void
    {
        $files = $this->service->findCompressableLogFiles('/nonexistent/path/laravel.log');

        $this->assertEmpty($files);
    }

    /**
     * Helper: assert string contains substring.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
