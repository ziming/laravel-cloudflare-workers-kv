<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ziming\LaravelCloudflareWorkersKv\Commands\LaravelCloudflareWorkersKvCommand;

final class LaravelCloudflareWorkersKvServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-cloudflare-workers-kv')
            ->hasConfigFile()
            ->hasCommand(LaravelCloudflareWorkersKvCommand::class);
    }
}
