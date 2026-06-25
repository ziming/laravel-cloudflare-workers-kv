<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

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

    public function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        return $this->client->put($this->prefix.$key, $this->serializer->serialize($value), $seconds);
    }

    public function delete(string $key): bool
    {
        return $this->client->delete($this->prefix.$key);
    }
}
