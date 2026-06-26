<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Tests\Fakes;

use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;

final class InMemoryCloudflareKvClient implements CloudflareKvClient
{
    /** @var array<string, string> */
    public array $values = [];

    /** @var array<string, int|null> */
    public array $expirations = [];

    /** @var array<string, array<string, mixed>> */
    public array $metadata = [];

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function getMetadata(string $key): ?array
    {
        if (! array_key_exists($key, $this->values)) {
            return null;
        }

        return $this->metadata[$key] ?? [];
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

    public function put(string $key, string $value, ?int $seconds = null, array $metadata = []): bool
    {
        $this->values[$key] = $value;
        $this->expirations[$key] = $seconds;
        $this->metadata[$key] = $metadata;

        return true;
    }

    public function putMany(array $entries): bool
    {
        foreach ($entries as $entry) {
            $value = ($entry['base64'] ?? false) ? base64_decode($entry['value'], true) ?: $entry['value'] : $entry['value'];
            $this->put($entry['key'], $value, $entry['seconds'] ?? null, $entry['metadata'] ?? []);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->expirations[$key], $this->metadata[$key]);

        return true;
    }

    public function deleteMany(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function keys(string $prefix = ''): array
    {
        return array_values(array_filter(
            array_keys($this->values),
            fn (string $key): bool => str_starts_with($key, $prefix),
        ));
    }
}
