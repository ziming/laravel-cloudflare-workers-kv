<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKvServiceProvider;

final class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Ziming\\LaravelCloudflareWorkersKv\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelCloudflareWorkersKvServiceProvider::class,
        ];
    }
}
