<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use GuzzleHttp\Client;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ziming\LaravelCloudflareWorkersKv\Serialization\SerializerFactory;

final class LaravelCloudflareWorkersKvServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-cloudflare-workers-kv')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CloudflareKvClient::class, function (Application $app): CloudflareKvClient {
            $config = $app['config']->get('cloudflare-workers-kv');

            $this->requireConfig($config, ['account_id', 'namespace_id', 'api_token']);

            return new RestCloudflareKvClient(
                new Client([
                    'base_uri' => mb_rtrim((string) $config['base_url'], '/').'/',
                    'headers' => [
                        'Authorization' => 'Bearer '.$config['api_token'],
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
                SerializerFactory::make(
                    (string) $config['serializer'],
                    $config['allowed_classes'] ?? null,
                ),
                (string) $config['prefix'],
            );
        });
    }

    public function packageBooted(): void
    {
        $this->app['cache']->extend('cloudflare-kv', function (Application $app, array $config): Repository {
            $globalConfig = $app['config']->get('cloudflare-workers-kv');

            $store = new CloudflareKvStore(
                $app->make(CloudflareKvClient::class),
                SerializerFactory::make(
                    (string) ($config['serializer'] ?? $globalConfig['serializer'] ?? 'php'),
                    $config['allowed_classes'] ?? $globalConfig['allowed_classes'] ?? null,
                ),
                (string) ($config['prefix'] ?? $globalConfig['prefix'] ?? ''),
            );

            return new Repository($store, $config);
        });
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $keys
     */
    private function requireConfig(array $config, array $keys): void
    {
        foreach ($keys as $key) {
            if (empty($config[$key])) {
                throw new InvalidArgumentException(
                    "Cloudflare KV configuration key [{$key}] is missing or empty. Check your cloudflare-workers-kv config.",
                );
            }
        }
    }
}
