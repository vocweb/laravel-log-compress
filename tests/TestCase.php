<?php

namespace vocweb\LaravelLogCompress\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use vocweb\LaravelLogCompress\LogCompressServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LogCompressServiceProvider::class,
        ];
    }
}
