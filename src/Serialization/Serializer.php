<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Serialization;

interface Serializer
{
    public function serialize(mixed $value): string;

    public function unserialize(string $value): mixed;
}
