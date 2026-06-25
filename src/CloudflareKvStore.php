<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use Illuminate\Contracts\Cache\Store;
use Ziming\LaravelCloudflareWorkersKv\Serialization\Serializer;

final class CloudflareKvStore implements Store
{
    public function __construct(
        private readonly CloudflareKvClient $client,
        private readonly Serializer $serializer,
        private readonly string $prefix = '',
    ) {}

    public function get($key): mixed
    {
        $value = $this->client->get($this->prefix.$key);

        return $value === null ? null : $this->serializer->unserialize($value);
    }

    public function many(array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function put($key, $value, $seconds): bool
    {
        if ($seconds <= 0) {
            return $this->forget($key);
        }

        return $this->client->put($this->prefix.$key, $this->serializer->serialize($value), (int) $seconds);
    }

    public function putMany(array $values, $seconds): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            $success = $this->put($key, $value, $seconds) && $success;
        }

        return $success;
    }

    public function increment($key, $value = 1): int|bool
    {
        $current = $this->get($key);

        if (! is_numeric($current)) {
            return false;
        }

        $newValue = (int) $current + (int) $value;

        return $this->forever($key, $newValue) ? $newValue : false;
    }

    public function decrement($key, $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    public function forever($key, $value): bool
    {
        return $this->client->put($this->prefix.$key, $this->serializer->serialize($value));
    }

    public function touch($key, $seconds): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return false;
        }

        return $this->put($key, $value, $seconds);
    }

    public function forget($key): bool
    {
        return $this->client->delete($this->prefix.$key);
    }

    public function flush(): bool
    {
        $success = true;

        foreach ($this->client->keys($this->prefix) as $key) {
            $success = $this->client->delete($key) && $success;
        }

        return $success;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
