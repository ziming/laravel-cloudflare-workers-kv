<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Serialization;

use InvalidArgumentException;

final class SerializerFactory
{
    /**
     * @param  array<class-string>|bool|null  $allowedClasses  Forwarded to PhpSerializer for safe unserialize().
     */
    public static function make(string $serializer, array|bool|null $allowedClasses = null): Serializer
    {
        return match ($serializer) {
            'php', 'serialize', 'serialized' => new PhpSerializer($allowedClasses),
            'json' => new JsonSerializer(),
            default => throw new InvalidArgumentException("Unsupported Cloudflare KV serializer [{$serializer}]."),
        };
    }
}
