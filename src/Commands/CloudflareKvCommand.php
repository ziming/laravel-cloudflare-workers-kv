<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvStore;
use Ziming\LaravelCloudflareWorkersKv\Serialization\JsonSerializer;
use Ziming\LaravelCloudflareWorkersKv\Serialization\PhpSerializer;
use Ziming\LaravelCloudflareWorkersKv\Serialization\Serializer;
use Ziming\LaravelCloudflareWorkersKv\Serialization\SerializerFactory;

abstract class CloudflareKvCommand extends Command
{
    /**
     * Resolve the Cloudflare KV store the command should act on.
     *
     * With --store=<name> the matching cache store is used (honouring any
     * per-store credentials). Without it the package's global configuration is
     * used directly.
     */
    protected function resolveStore(): CloudflareKvStore
    {
        $name = $this->option('store');

        if (is_string($name) && $name !== '') {
            $store = $this->laravel->make('cache')->store($name)->getStore();

            if (! $store instanceof CloudflareKvStore) {
                throw new InvalidArgumentException("Cache store [{$name}] is not a Cloudflare KV store.");
            }

            return $store;
        }

        $config = $this->laravel->make('config')->get('cloudflare-workers-kv');
        $config = is_array($config) ? $config : [];

        return new CloudflareKvStore(
            $this->laravel->make(CloudflareKvClient::class),
            SerializerFactory::make(
                (string) ($config['serializer'] ?? 'php'),
                $config['allowed_classes'] ?? null,
            ),
            (string) ($config['prefix'] ?? ''),
            (bool) ($config['graceful'] ?? false),
        );
    }

    protected function serializerName(Serializer $serializer): string
    {
        return match (true) {
            $serializer instanceof JsonSerializer => 'json',
            $serializer instanceof PhpSerializer => 'php',
            default => $serializer::class,
        };
    }
}
