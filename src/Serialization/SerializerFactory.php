<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Serialization;

use InvalidArgumentException;

final class SerializerFactory
{
    public static function make(string $serializer): Serializer
    {
        return match ($serializer) {
            'php', 'serialize', 'serialized' => new PhpSerializer(),
            'json' => new JsonSerializer(),
            default => throw new InvalidArgumentException("Unsupported Cloudflare KV serializer [{$serializer}]."),
        };
    }
}
