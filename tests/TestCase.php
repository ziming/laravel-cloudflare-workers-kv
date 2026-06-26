<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKvServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelCloudflareWorkersKvServiceProvider::class,
        ];
    }
}
