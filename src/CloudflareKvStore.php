<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use Illuminate\Contracts\Cache\Store;
use InvalidArgumentException;
use Throwable;
use Ziming\LaravelCloudflareWorkersKv\Serialization\PhpSerializer;
use Ziming\LaravelCloudflareWorkersKv\Serialization\Serializer;

/**
 * Cloudflare Workers KV cache store.
 *
 * KV is an eventually-consistent, globally-distributed key/value store. Writes
 * propagate globally and may not be immediately visible to other readers.
 * It is NOT suitable for:
 *   - Atomic counters or rate limiting (no atomic increment; reads may be stale)
 *   - Cache locks (use a database or Redis store instead)
 *   - TTLs shorter than 60 seconds (Cloudflare enforces a 60-second minimum)
 *
 * It is well-suited for read-heavy workloads with large geographic distribution.
 */
final class CloudflareKvStore implements Store
{
    public function __construct(
        private readonly CloudflareKvClient $client,
        private readonly Serializer $serializer,
        private readonly string $prefix = '',
        private readonly bool $graceful = false,
    ) {}

    public function get($key): mixed
    {
        $this->validateKey($key);

        try {
            $value = $this->client->get($this->prefix.$key);
        } catch (CloudflareKvException $exception) {
            return $this->onReadFailure($exception);
        }

        return $this->deserialize($value);
    }

    public function many(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        $prefixed = array_combine(
            array_map(fn (string $k): string => $this->prefix.$k, $keys),
            $keys,
        );

        $results = array_fill_keys($keys, null);

        try {
            $raw = $this->client->many(array_keys($prefixed));
        } catch (CloudflareKvException $exception) {
            $this->onReadFailure($exception);

            return $results;
        }

        foreach ($raw as $prefixedKey => $value) {
            if (isset($prefixed[$prefixedKey])) {
                $results[$prefixed[$prefixedKey]] = $this->deserialize($value);
            }
        }

        return $results;
    }

    public function put($key, $value, $seconds): bool
    {
        $this->validateKey($key);

        if ($seconds <= 0) {
            return $this->forget($key);
        }

        return $this->client->put(
            $this->prefix.$key,
            $this->serializer->serialize($value),
            (int) $seconds,
        );
    }

    public function putMany(array $values, $seconds): bool
    {
        if ($seconds <= 0) {
            $success = true;
            foreach ($values as $key => $value) {
                $success = $this->forget($key) && $success;
            }

            return $success;
        }

        $ttl = (int) $seconds;
        $useBase64 = $this->serializer instanceof PhpSerializer;

        $entries = [];
        foreach ($values as $key => $value) {
            $this->validateKey((string) $key);
            $entries[] = [
                'key' => $this->prefix.$key,
                'value' => $this->serializer->serialize($value),
                'seconds' => $ttl,
                'base64' => $useBase64,
            ];
        }

        return $this->client->putMany($entries);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * Note: KV does not support atomic increment. This performs a non-atomic
     * read-modify-write. Under concurrent writes, updates may be lost.
     *
     * The key's exact expiry is preserved: the value and its absolute expiration
     * are read in one call, then re-written with that same absolute expiration
     * (or forever when the key has none), so a hot counter does not have its
     * lifetime extended on every increment.
     */
    public function increment($key, $value = 1): int|bool
    {
        $this->validateKey($key);

        $entry = $this->client->getWithMetadata($this->prefix.$key);

        if ($entry === null) {
            return false;
        }

        $current = $this->deserialize($entry['value']);

        if (! is_numeric($current)) {
            return false;
        }

        $expiration = $entry['expiration'];

        if ($expiration !== null && $expiration <= time()) {
            return false;
        }

        $newValue = (int) $current + (int) $value;

        return $this->client->put(
            $this->prefix.$key,
            $this->serializer->serialize($newValue),
            null,
            $expiration,
        ) ? $newValue : false;
    }

    public function decrement($key, $value = 1): int|bool
    {
        return $this->increment($key, -(int) $value);
    }

    public function forever($key, $value): bool
    {
        $this->validateKey($key);

        return $this->client->put($this->prefix.$key, $this->serializer->serialize($value));
    }

    public function touch($key, $seconds): bool
    {
        $this->validateKey($key);

        $value = $this->get($key);

        if ($value === null) {
            return false;
        }

        return $this->put($key, $value, $seconds);
    }

    public function forget($key): bool
    {
        $this->validateKey($key);

        return $this->client->delete($this->prefix.$key);
    }

    public function flush(): bool
    {
        $keys = $this->client->keys($this->prefix);

        if ($keys === []) {
            return true;
        }

        return $this->client->deleteMany($keys);
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * The underlying key/value client. Exposed for operator tooling (artisan
     * commands) so they target the same namespace this store is configured for.
     */
    public function client(): CloudflareKvClient
    {
        return $this->client;
    }

    public function serializer(): Serializer
    {
        return $this->serializer;
    }

    /**
     * Deserialize a stored value, treating any failure (corrupt or foreign data)
     * as a cache miss rather than letting it bubble out of get()/many().
     */
    private function deserialize(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            return $this->serializer->unserialize($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * When 'graceful' is enabled a read failure (KV outage) degrades to a cache
     * miss instead of throwing. Default behaviour re-throws so failures are loud.
     */
    private function onReadFailure(CloudflareKvException $exception): mixed
    {
        if ($this->graceful) {
            return null;
        }

        throw $exception;
    }

    /**
     * Cloudflare KV requires keys to be non-empty, at most 512 bytes, and
     * contain only printable non-whitespace characters.
     */
    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key must not be empty.');
        }

        if (mb_strlen($this->prefix.$key) > 512) {
            throw new InvalidArgumentException(
                sprintf('Cache key "%s" with prefix exceeds the 512-byte Cloudflare KV limit.', $key),
            );
        }

        if (preg_match('/\s/', $key)) {
            throw new InvalidArgumentException(
                sprintf('Cache key "%s" must not contain whitespace characters.', $key),
            );
        }
    }
}
