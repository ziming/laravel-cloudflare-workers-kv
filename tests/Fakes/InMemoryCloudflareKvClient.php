<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Tests\Fakes;

use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;

final class InMemoryCloudflareKvClient implements CloudflareKvClient
{
    /**
     * @var array<string, string>
     */
    public array $values = [];

    /**
     * @var array<string, int|null>
     */
    public array $expirations = [];

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function put(string $key, string $value, ?int $seconds = null): bool
    {
        $this->values[$key] = $value;
        $this->expirations[$key] = $seconds;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->expirations[$key]);

        return true;
    }

    public function keys(string $prefix = ''): array
    {
        return array_values(array_filter(
            array_keys($this->values),
            fn (string $key): bool => str_starts_with($key, $prefix),
        ));
    }
}
