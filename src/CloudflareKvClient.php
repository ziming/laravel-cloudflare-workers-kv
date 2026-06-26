<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

interface CloudflareKvClient
{
    public function get(string $key): ?string;

    /**
     * Retrieve multiple values in one request (chunks of 100 per Cloudflare limit).
     * Missing keys are absent from the returned map (not null).
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function many(array $keys): array;

    /**
     * Read the metadata stored alongside a key, or null if the key does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(string $key): ?array;

    public function put(string $key, string $value, ?int $seconds = null, array $metadata = []): bool;

    /**
     * Write multiple key/value pairs in bulk (up to 10,000 per request).
     *
     * @param  list<array{key:string,value:string,seconds?:int|null,base64?:bool,metadata?:array<string,mixed>}>  $entries
     */
    public function putMany(array $entries): bool;

    public function delete(string $key): bool;

    /**
     * Delete multiple keys in bulk (up to 10,000 per request).
     *
     * @param  list<string>  $keys
     */
    public function deleteMany(array $keys): bool;

    /**
     * @return list<string>
     */
    public function keys(string $prefix = ''): array;
}
