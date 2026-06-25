<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Serialization;

final class PhpSerializer implements Serializer
{
    public function serialize(mixed $value): string
    {
        return serialize($value);
    }

    public function unserialize(string $value): mixed
    {
        return unserialize($value);
    }
}
