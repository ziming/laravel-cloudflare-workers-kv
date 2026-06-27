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
     * Values are read as text; non-UTF-8 (binary) payloads cannot round-trip
     * through the bulk endpoint and are omitted. Use {@see self::get()} for those.
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function many(array $keys): array;

    /**
     * Read a key's value together with its absolute expiration and metadata,
     * or null if the key does not exist.
     *
     * The expiration is an absolute unix timestamp (or null when the key never
     * expires). Implemented via the bulk get endpoint with `withMetadata`.
     *
     * @return array{value: string, expiration: int|null, metadata: array<string, mixed>}|null
     */
    public function getWithMetadata(string $key): ?array;

    /**
     * Write a single value.
     *
     * Provide $expiration (absolute unix timestamp) to pin an exact expiry, or
     * $ttl (seconds from now) for a relative one. When both are null the key
     * never expires. Cloudflare enforces a 60-second floor on either form.
     */
    public function put(string $key, string $value, ?int $ttl = null, ?int $expiration = null): bool;

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
     * List keys, optionally filtered by prefix.
     *
     * When $limit is null all keys are returned (following Cloudflare's cursor
     * pagination). When $limit is set a single page of at most $limit keys is
     * returned without paginating — handy for lightweight connectivity checks.
     *
     * @return list<string>
     */
    public function keys(string $prefix = '', ?int $limit = null): array;
}
