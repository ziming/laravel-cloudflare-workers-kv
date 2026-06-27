<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Tests\Fakes;

use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvException;

/**
 * A client that simulates a Cloudflare KV outage by throwing from every call.
 */
final class ThrowingCloudflareKvClient implements CloudflareKvClient
{
    public function get(string $key): ?string
    {
        throw new CloudflareKvException('Cloudflare KV is unavailable.');
    }

    public function getWithMetadata(string $key): ?array
    {
        throw new CloudflareKvException('Cloudflare KV is unavailable.');
    }

    public function many(array $keys): array
    {
        throw new CloudflareKvException('Cloudflare KV is unavailable.');
    }

    public function put(string $key, string $value, ?int $ttl = null, ?int $expiration = null): bool
    {
        throw new CloudflareKvException('Cloudflare KV is unavailable.');
    }

    public function putMany(array $entries): bool
    {
        throw new CloudflareKvException('Cloudflare KV is unavailable.');
    }

    public function delete(string $key): bool
    {
        throw new CloudflareKvException('Cloudflare KV is unavailable.');
    }

    public function deleteMany(array $keys): bool
    {
        throw new CloudflareKvException('Cloudflare KV is unavailable.');
    }

    public function keys(string $prefix = '', ?int $limit = null): array
    {
        throw new CloudflareKvException('Cloudflare KV is unavailable.');
    }
}
