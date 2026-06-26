<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use Ziming\LaravelCloudflareWorkersKv\Serialization\PhpSerializer;
use Ziming\LaravelCloudflareWorkersKv\Serialization\Serializer;
use Ziming\LaravelCloudflareWorkersKv\Serialization\SerializerFactory;

final readonly class LaravelCloudflareWorkersKv
{
    public function __construct(
        private CloudflareKvClient $client,
        private Serializer $serializer,
        private string $prefix = '',
    ) {}

    public static function json(CloudflareKvClient $client, string $prefix = ''): self
    {
        return new self($client, SerializerFactory::make('json'), $prefix);
    }

    public static function serialized(CloudflareKvClient $client, string $prefix = ''): self
    {
        return new self($client, SerializerFactory::make('php'), $prefix);
    }

    public function get(string $key): mixed
    {
        $value = $this->client->get($this->prefix.$key);

        return $value === null ? null : $this->serializer->unserialize($value);
    }

    /**
     * Retrieve multiple values in one bulk request. Missing keys map to null.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        if ($keys === []) {
            return [];
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

    public function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        return $this->client->put($this->prefix.$key, $this->serializer->serialize($value), $seconds);
    }

    /**
     * Write multiple key/value pairs in one bulk request.
     *
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values, ?int $seconds = null): bool
    {
        if ($values === []) {
            return true;
        }

        $useBase64 = $this->serializer instanceof PhpSerializer;

        $entries = [];
        foreach ($values as $key => $value) {
            $entries[] = [
                'key' => $this->prefix.$key,
                'value' => $this->serializer->serialize($value),
                'seconds' => $seconds,
                'base64' => $useBase64,
            ];
        }

        return $this->client->putMany($entries);
    }

    public function delete(string $key): bool
    {
        return $this->client->delete($this->prefix.$key);
    }

    /**
     * Delete multiple keys in one bulk request.
     *
     * @param  list<string>  $keys
     */
    public function deleteMany(array $keys): bool
    {
        if ($keys === []) {
            return true;
        }

        return $this->client->deleteMany(array_map(fn (string $k): string => $this->prefix.$k, $keys));
    }
}
