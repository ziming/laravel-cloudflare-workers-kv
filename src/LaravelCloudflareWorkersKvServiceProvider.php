<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ziming\LaravelCloudflareWorkersKv\Commands\GetCommand;
use Ziming\LaravelCloudflareWorkersKv\Commands\KeysCommand;
use Ziming\LaravelCloudflareWorkersKv\Commands\VerifyCommand;
use Ziming\LaravelCloudflareWorkersKv\Serialization\SerializerFactory;

final class LaravelCloudflareWorkersKvServiceProvider extends PackageServiceProvider
{
    /**
     * Config keys that select which Cloudflare namespace/connection a client targets.
     * When a cache store overrides any of these it gets its own client instance.
     */
    private const array CLIENT_KEYS = [
        'account_id',
        'namespace_id',
        'api_token',
        'base_url',
        'timeout',
        'connect_timeout',
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-cloudflare-workers-kv')
            ->hasConfigFile()
            ->hasCommands([
                VerifyCommand::class,
                KeysCommand::class,
                GetCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CloudflareKvClient::class, function (Application $app): CloudflareKvClient {
            return CloudflareKvClientFactory::make(self::globalConfig($app));
        });

        $this->app->singleton(LaravelCloudflareWorkersKv::class, function (Application $app): LaravelCloudflareWorkersKv {
            $config = self::globalConfig($app);

            return new LaravelCloudflareWorkersKv(
                $app->make(CloudflareKvClient::class),
                SerializerFactory::make(
                    (string) ($config['serializer'] ?? 'php'),
                    $config['allowed_classes'] ?? null,
                ),
                (string) ($config['prefix'] ?? ''),
            );
        });
    }

    public function packageBooted(): void
    {
        // The cache manager invokes this closure with its own scope, so it must
        // not reference $this/self — everything it needs is passed in or static.
        $clientKeys = self::CLIENT_KEYS;

        $this->app['cache']->extend('cloudflare-kv', function (Application $app, array $config) use ($clientKeys): Repository {
            $global = $app['config']->get('cloudflare-workers-kv');
            $merged = array_merge(is_array($global) ? $global : [], $config);

            // Only build a dedicated client when the store overrides connection
            // credentials; otherwise reuse the shared singleton (back-compatible).
            $client = array_intersect_key($config, array_flip($clientKeys)) !== []
                ? CloudflareKvClientFactory::make($merged)
                : $app->make(CloudflareKvClient::class);

            $store = new CloudflareKvStore(
                $client,
                SerializerFactory::make(
                    (string) ($merged['serializer'] ?? 'php'),
                    $merged['allowed_classes'] ?? null,
                ),
                (string) ($merged['prefix'] ?? ''),
                (bool) ($merged['graceful'] ?? false),
            );

            return new Repository($store, $config);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function globalConfig(Application $app): array
    {
        $config = $app['config']->get('cloudflare-workers-kv');

        return is_array($config) ? $config : [];
    }
}
