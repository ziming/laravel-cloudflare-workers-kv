<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use Illuminate\Contracts\Cache\Store;
use InvalidArgumentException;
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
    /**
     * Metadata key used to track a key's absolute expiry so increment() can
     * preserve the remaining TTL across the non-atomic read-modify-write.
     */
    private const string META_EXPIRES_AT = 'e';

    public function __construct(
        private readonly CloudflareKvClient $client,
        private readonly Serializer $serializer,
        private readonly string $prefix = '',
    ) {}

    public function get($key): mixed
    {
        $this->validateKey($key);

        $value = $this->client->get($this->prefix.$key);

        return $value === null ? null : $this->serializer->unserialize($value);
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

        $raw = $this->client->many(array_keys($prefixed));

        $results = array_fill_keys($keys, null);

        foreach ($raw as $prefixedKey => $value) {
            if (isset($prefixed[$prefixedKey])) {
                $results[$prefixed[$prefixedKey]] = $this->serializer->unserialize($value);
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

        $ttl = (int) $seconds;
        $metadata = [self::META_EXPIRES_AT => time() + $ttl];

        return $this->client->put(
            $this->prefix.$key,
            $this->serializer->serialize($value),
            $ttl,
            $metadata,
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
        $expiresAt = time() + $ttl;
        $serializer = $this->serializer;
        $useBase64 = $serializer instanceof \Ziming\LaravelCloudflareWorkersKv\Serialization\PhpSerializer;

        $entries = [];
        foreach ($values as $key => $value) {
            $this->validateKey((string) $key);
            $serialized = $serializer->serialize($value);
            $entries[] = [
                'key' => $this->prefix.$key,
                'value' => $serialized,
                'seconds' => $ttl,
                'base64' => $useBase64,
                'metadata' => [self::META_EXPIRES_AT => $expiresAt],
            ];
        }

        return $this->client->putMany($entries);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * Note: KV does not support atomic increment. This performs a non-atomic
     * read-modify-write. Under concurrent writes, updates may be lost.
     * The remaining TTL is approximated from metadata written at the last put().
     */
    public function increment($key, $value = 1): int|bool
    {
        $this->validateKey($key);

        $current = $this->get($key);

        if (! is_numeric($current)) {
            return false;
        }

        $newValue = (int) $current + (int) $value;

        $metadata = $this->client->getMetadata($this->prefix.$key);
        $expiresAt = is_array($metadata) && is_int($metadata[self::META_EXPIRES_AT] ?? null)
            ? $metadata[self::META_EXPIRES_AT]
            : null;

        if ($expiresAt !== null) {
            $remaining = $expiresAt - time();
            if ($remaining <= 0) {
                return false;
            }

            return $this->client->put(
                $this->prefix.$key,
                $this->serializer->serialize($newValue),
                $remaining,
                [self::META_EXPIRES_AT => $expiresAt],
            ) ? $newValue : false;
        }

        return $this->forever($key, $newValue) ? $newValue : false;
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
     * Cloudflare KV requires keys to be non-empty, at most 512 bytes, and
     * contain only printable non-whitespace characters.
     */
    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key must not be empty.');
        }

        if (strlen($this->prefix.$key) > 512) {
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
