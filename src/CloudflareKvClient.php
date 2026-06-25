<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

interface CloudflareKvClient
{
    public function get(string $key): ?string;

    public function put(string $key, string $value, ?int $seconds = null): bool;

    public function delete(string $key): bool;

    /**
     * @return list<string>
     */
    public function keys(string $prefix = ''): array;
}
