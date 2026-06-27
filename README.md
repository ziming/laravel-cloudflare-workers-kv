# Laravel package for Cloudflare Workers KV

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ziming/laravel-cloudflare-workers-kv.svg?style=flat-square)](https://packagist.org/packages/ziming/laravel-cloudflare-workers-kv)
[![GitHub Tests Action Status](https://github.com/ziming/laravel-cloudflare-workers-kv/actions/workflows/run-tests.yml/badge.svg)](https://github.com/ziming/laravel-cloudflare-workers-kv/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/ziming/laravel-cloudflare-workers-kv/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/ziming/laravel-cloudflare-workers-kv/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ziming/laravel-cloudflare-workers-kv.svg?style=flat-square)](https://packagist.org/packages/ziming/laravel-cloudflare-workers-kv)

Use Cloudflare Workers KV as a Laravel cache store or as a small key/value client. Values can be stored with Laravel-compatible PHP serialization or as plain JSON for easy reads from other Cloudflare Workers.

## Installation

You can install the package via composer:

```bash
composer require ziming/laravel-cloudflare-workers-kv
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-cloudflare-workers-kv-config"
```

This is the contents of the published config file:

```php
return [
    'account_id' => env('CLOUDFLARE_KV_ACCOUNT_ID'),

    'namespace_id' => env('CLOUDFLARE_KV_NAMESPACE_ID'),

    'api_token' => env('CLOUDFLARE_KV_API_TOKEN'),

    'base_url' => env('CLOUDFLARE_KV_BASE_URL', 'https://api.cloudflare.com/client/v4'),

    // HTTP timeouts (seconds) so a hung connection never blocks the request.
    'timeout' => env('CLOUDFLARE_KV_TIMEOUT', 5),
    'connect_timeout' => env('CLOUDFLARE_KV_CONNECT_TIMEOUT', 2),

    // When true, read failures (KV outage) degrade to a cache miss instead of
    // throwing. See "Graceful reads" below.
    'graceful' => env('CLOUDFLARE_KV_GRACEFUL', false),

    'serializer' => env('CLOUDFLARE_KV_SERIALIZER', 'php'),

    // Restricts which classes may be instantiated when unserializing PHP-serialized
    // values. Use an array of class-strings to allowlist, false to forbid all objects,
    // or null (default) to allow all. See "Security" below.
    'allowed_classes' => null,

    'prefix' => env('CLOUDFLARE_KV_PREFIX', ''),
];
```

Add your Cloudflare credentials to `.env`:

```dotenv
CLOUDFLARE_KV_ACCOUNT_ID=your-account-id
CLOUDFLARE_KV_NAMESPACE_ID=your-namespace-id
CLOUDFLARE_KV_API_TOKEN=your-api-token
```

## Usage

### Laravel cache store

Add a cache store to `config/cache.php`:

```php
'stores' => [
    'cloudflare' => [
        'driver' => 'cloudflare-kv',
        'serializer' => 'php',
        'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),
    ],
],
```

Then use it like any other Laravel cache store:

```php
Cache::store('cloudflare')->put('user:1', ['name' => 'Ada'], 3600);

$user = Cache::store('cloudflare')->get('user:1');
```

The default `php` serializer uses PHP `serialize()` and `unserialize()`, matching the behavior expected by Laravel applications storing arrays, objects, booleans, and numbers in cache.

### JSON key/value pairs

Use the `json` serializer when other Cloudflare Workers should read the values directly:

```php
'stores' => [
    'cloudflare-json' => [
        'driver' => 'cloudflare-kv',
        'serializer' => 'json',
        'prefix' => 'shared:',
    ],
],
```

```php
Cache::store('cloudflare-json')->forever('feature-flags', [
    'checkout' => true,
    'limit' => 5,
]);
```

That stores this raw KV value:

```json
{"checkout":true,"limit":5}
```

From a Worker, read it as ordinary JSON:

```ts
const flags = await env.KV.get("shared:feature-flags", "json");
```

### Multiple namespaces

Every value in `config/cloudflare-workers-kv.php` is a default that any cache store may
override in `config/cache.php`, so a single app can target several KV namespaces:

```php
'stores' => [
    'cloudflare' => [
        'driver' => 'cloudflare-kv',
    ],

    'cloudflare-sessions' => [
        'driver'       => 'cloudflare-kv',
        'namespace_id' => env('CLOUDFLARE_KV_SESSIONS_NAMESPACE_ID'),
        'prefix'       => 'sess:',
    ],
],
```

A store may override `account_id`, `namespace_id`, `api_token`, `base_url`, `timeout`,
`connect_timeout`, `serializer`, `allowed_classes`, `prefix`, and `graceful`. Overriding any
of the first six gives that store its own HTTP client; otherwise it shares the global one.
Omitted keys fall back to the global config, so existing single-store setups keep working.

### Graceful reads

By default a KV outage (5xx / connection error) surfaces as a `CloudflareKvException` from
reads. Set `'graceful' => true` (globally or per store) to make reads fail open — a failed
`get()`/`many()` returns a cache miss (`null`) instead of throwing:

```php
'cloudflare' => [
    'driver'   => 'cloudflare-kv',
    'graceful' => true,
],
```

This trades loud failures for availability. Note that failing open can let a KV outage
unleash a thundering herd onto whatever the cache is protecting, so weigh it per workload.

### Artisan commands

Laravel's built-in `cache:clear --store=…` and `cache:forget … --store=…` work as usual. This
package adds a few KV-specific helpers (all accept `--store=` and fall back to the global
config when it is omitted):

```bash
# Validate credentials + connectivity and print the resolved configuration.
php artisan cloudflare-kv:verify --store=cloudflare

# List keys (optionally filtered) for debugging.
php artisan cloudflare-kv:keys --store=cloudflare --prefix=user:

# Fetch a single value (deserialized, or --raw for the stored bytes).
php artisan cloudflare-kv:get user:1 --store=cloudflare
php artisan cloudflare-kv:get user:1 --store=cloudflare --raw
```

### Direct client

You can also resolve the package client directly:

```php
use Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKv;

$kv = app(LaravelCloudflareWorkersKv::class);

$kv->put('settings', ['theme' => 'dark'], 3600); // optional TTL in seconds
$kv->forever('settings', ['theme' => 'dark']);    // no expiry

$settings = $kv->get('settings');

// Read a value alongside its absolute expiry (unix timestamp, null if none):
$entry = $kv->getWithMetadata('settings'); // ['value' => ..., 'expiration' => ..., 'metadata' => [...]]
$expiresAt = $kv->expiresAt('settings');   // ?int
```

The client also exposes bulk helpers, which use Cloudflare's bulk REST endpoints
(one request per batch instead of one request per key):

```php
$kv->putMany(['a' => 1, 'b' => 2], 3600);   // PUT .../bulk  (up to 10,000 keys/request)

$values = $kv->many(['a', 'b', 'c']);        // POST .../bulk/get (up to 100 keys/request)
// => ['a' => 1, 'b' => 2, 'c' => null]      // missing keys are null

$kv->deleteMany(['a', 'b']);                  // POST .../bulk/delete (up to 10,000 keys/request)
```

The same bulk endpoints back `Cache::many()`, `Cache::putMany()`, and `Cache::flush()`
on the cache store, so flushing or warming many keys does not fan out into N HTTP calls.

> **Binary values and `many()`.** Cloudflare's bulk-get endpoint returns values as text/JSON
> and cannot carry non-UTF-8 bytes, so a binary value (e.g. a `php`-serialized payload that
> contains raw binary strings) is silently dropped from a `many()` result even though the key
> exists. PHP `serialize()` of typical scalars/arrays is ASCII and unaffected, but if you store
> binary blobs use the `json` serializer or read them one at a time with `get()`, which streams
> the raw body and is binary-safe.

## Caveats & consistency model

Cloudflare Workers KV is an **eventually consistent, globally distributed** store. Its
characteristics differ from Redis/Memcached, so keep the following in mind before choosing
it as your cache backend:

- **Reads can be stale.** After a write, other edge locations may serve the previous value
  for a short period while the change propagates globally. KV is optimized for read-heavy
  workloads, not read-after-write consistency.
- **No atomic operations.** `increment()` / `decrement()` are implemented as a non-atomic
  read-modify-write. Concurrent writers can lose updates. The key's exact expiry is preserved:
  the value and its native absolute expiration are read together, then re-written with that
  same expiration, so a hot counter is not kept alive forever — but the counter value itself
  is best-effort. **Do not use this store for rate limiting** (`RateLimiter`) where exact
  counts matter under concurrency.
- **No cache locks.** The store does not implement `LockProvider`, so `Cache::lock()` is not
  available — KV cannot provide the atomic guarantees a lock requires. Use the `database` or
  `redis` store for locks.
- **60-second minimum TTL.** Cloudflare enforces a 60-second floor on `expiration_ttl`. TTLs
  below 60 seconds are silently raised to 60, so sub-minute expirations behave as one minute.
- **Key constraints.** Keys (including the configured `prefix`) must be non-empty, at most
  512 bytes, and contain no whitespace. Invalid keys throw an `InvalidArgumentException`.

In short: KV is a great fit for read-heavy, geographically distributed caching, and a poor
fit for locks, atomic counters, and anything needing strong consistency.

## Security

When using the `php` serializer, cached values are restored with PHP's `unserialize()`. If the
KV namespace is shared with, or writable by, untrusted parties, a malicious payload could trigger
PHP object injection. Restrict which classes may be instantiated via the `allowed_classes` config
option:

```php
// config/cloudflare-workers-kv.php
'allowed_classes' => false,                          // forbid all objects (scalars/arrays only)
// or
'allowed_classes' => [App\Dto\FeatureFlags::class],  // allowlist specific classes
```

The `json` serializer does not call `unserialize()` and is not affected.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [ziming](https://github.com/ziming)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
