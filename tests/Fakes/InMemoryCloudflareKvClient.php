<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Tests\Fakes;

use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;

final class InMemoryCloudflareKvClient implements CloudflareKvClient
{
    /** @var array<string, string> */
    public array $values = [];

    /** @var array<string, int|null> Last TTL (seconds) passed to put(), or null for forever. */
    public array $ttls = [];

    /** @var array<string, int|null> Resolved absolute expiry (unix ts), or null for forever. */
    public array $expirations = [];

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function getWithMetadata(string $key): ?array
    {
        if (! array_key_exists($key, $this->values)) {
            return null;
        }

        return [
            'value' => $this->values[$key],
            'expiration' => $this->expirations[$key] ?? null,
            'metadata' => [],
        ];
    }

    public function many(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $this->values)) {
                $results[$key] = $this->values[$key];
            }
        }

        return $results;
    }

    public function put(string $key, string $value, ?int $ttl = null, ?int $expiration = null): bool
    {
        $this->values[$key] = $value;
        $this->ttls[$key] = $ttl;

        $this->expirations[$key] = match (true) {
            $expiration !== null => $expiration,
            $ttl !== null => time() + $ttl,
            default => null,
        };

        return true;
    }

    public function putMany(array $entries): bool
    {
        foreach ($entries as $entry) {
            $value = ($entry['base64'] ?? false) ? (base64_decode($entry['value'], true) ?: $entry['value']) : $entry['value'];
            $this->put($entry['key'], $value, $entry['seconds'] ?? null);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->ttls[$key], $this->expirations[$key]);

        return true;
    }

    public function deleteMany(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function keys(string $prefix = '', ?int $limit = null): array
    {
        $keys = array_values(array_filter(
            array_keys($this->values),
            fn (string $key): bool => str_starts_with($key, $prefix),
        ));

        return $limit !== null ? array_slice($keys, 0, $limit) : $keys;
    }
}
