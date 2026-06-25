<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Serialization;

use JsonException;

final class JsonSerializer implements Serializer
{
    /**
     * @throws JsonException
     */
    public function serialize(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    public function unserialize(string $value): mixed
    {
        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }
}
