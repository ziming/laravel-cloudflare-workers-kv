# Laravel package for Cloudflare Workers KV

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ziming/laravel-cloudflare-workers-kv.svg?style=flat-square)](https://packagist.org/packages/ziming/laravel-cloudflare-workers-kv)
[![GitHub Tests Action Status](https://github.com/spatie/package-laravel-cloudflare-workers-kv-laravel/actions/workflows/run-tests.yml/badge.svg)](https://github.com/ziming/laravel-cloudflare-workers-kv/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/spatie/package-laravel-cloudflare-workers-kv-laravel/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/ziming/laravel-cloudflare-workers-kv/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
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

    'serializer' => env('CLOUDFLARE_KV_SERIALIZER', 'php'),

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

### Direct client

You can also resolve the package client directly:

```php
use Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKv;

$kv = app(LaravelCloudflareWorkersKv::class);

$kv->put('settings', ['theme' => 'dark']);

$settings = $kv->get('settings');
```

Cloudflare Workers KV requires `expiration_ttl` values to be at least 60 seconds. This package sends cache TTLs to Cloudflare using that API constraint.

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
