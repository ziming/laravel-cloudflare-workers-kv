<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Serialization;

final class PhpSerializer implements Serializer
{
    /**
     * @param  array<class-string>|bool|null  $allowedClasses  Passed to unserialize()'s allowed_classes option.
     *                                                         null = allow all (default); false = allow none; array = allowlist.
     */
    public function __construct(
        private readonly array|bool|null $allowedClasses = null,
    ) {}

    public function serialize(mixed $value): string
    {
        return serialize($value);
    }

    public function unserialize(string $value): mixed
    {
        $options = $this->allowedClasses !== null
            ? ['allowed_classes' => $this->allowedClasses]
            : [];

        return unserialize($value, $options);
    }
}
