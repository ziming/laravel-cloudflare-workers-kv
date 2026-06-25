<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use GuzzleHttp\Client;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ziming\LaravelCloudflareWorkersKv\Commands\LaravelCloudflareWorkersKvCommand;
use Ziming\LaravelCloudflareWorkersKv\Serialization\SerializerFactory;

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

    public function packageRegistered(): void
    {
        $this->app->singleton(CloudflareKvClient::class, function (Application $app): CloudflareKvClient {
            $config = $app['config']->get('cloudflare-workers-kv');

            return new RestCloudflareKvClient(
                new Client([
                    'base_uri' => mb_rtrim((string) $config['base_url'], '/').'/',
                    'headers' => [
                        'Authorization' => 'Bearer '.$config['api_token'],
                        'Content-Type' => 'text/plain',
                    ],
                    'http_errors' => true,
                ]),
                (string) $config['account_id'],
                (string) $config['namespace_id'],
            );
        });

        $this->app->singleton(LaravelCloudflareWorkersKv::class, function (Application $app): LaravelCloudflareWorkersKv {
            $config = $app['config']->get('cloudflare-workers-kv');

            return new LaravelCloudflareWorkersKv(
                $app->make(CloudflareKvClient::class),
                SerializerFactory::make((string) $config['serializer']),
                (string) $config['prefix'],
            );
        });
    }

    public function packageBooted(): void
    {
        $this->app['cache']->extend('cloudflare-kv', function (Application $app, array $config): Repository {
            $store = new CloudflareKvStore(
                $app->make(CloudflareKvClient::class),
                SerializerFactory::make((string) ($config['serializer'] ?? $app['config']->get('cloudflare-workers-kv.serializer', 'php'))),
                (string) ($config['prefix'] ?? $app['config']->get('cloudflare-workers-kv.prefix', '')),
            );

            return new Repository($store, $config);
        });
    }
}
