<?php

declare(strict_types=1);

// config for Ziming/LaravelCloudflareWorkersKv
return [
    'account_id' => env('CLOUDFLARE_KV_ACCOUNT_ID'),

    'namespace_id' => env('CLOUDFLARE_KV_NAMESPACE_ID'),

    'api_token' => env('CLOUDFLARE_KV_API_TOKEN'),

    'base_url' => env('CLOUDFLARE_KV_BASE_URL', 'https://api.cloudflare.com/client/v4'),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeouts (seconds)
    |--------------------------------------------------------------------------
    |
    | A cache should fail fast: never let a hung Cloudflare connection block the
    | request indefinitely. 'timeout' caps the whole request, 'connect_timeout'
    | caps establishing the TCP/TLS connection.
    |
    */
    'timeout' => env('CLOUDFLARE_KV_TIMEOUT', 5),

    'connect_timeout' => env('CLOUDFLARE_KV_CONNECT_TIMEOUT', 2),

    /*
    |--------------------------------------------------------------------------
    | Graceful reads
    |--------------------------------------------------------------------------
    |
    | When true, a read failure (KV outage / 5xx / connection error) degrades to
    | a cache miss (null) instead of throwing. This trades loud failures for
    | fail-open behaviour — convenient, but a KV outage can then unleash a
    | thundering herd onto whatever the cache is protecting. Default: false.
    |
    */
    'graceful' => env('CLOUDFLARE_KV_GRACEFUL', false),

    /*
    |--------------------------------------------------------------------------
    | Serializer
    |--------------------------------------------------------------------------
    |
    | 'php'  — PHP serialize() / unserialize(). Stores arrays, objects, and
    |           scalar types faithfully. Use for pure-Laravel cache stores.
    |
    | 'json' — JSON encode/decode. Values are human-readable and can be consumed
    |           directly by other Cloudflare Workers. Suitable for sharing data
    |           across Workers but cannot store arbitrary PHP objects.
    |
    */
    'serializer' => env('CLOUDFLARE_KV_SERIALIZER', 'php'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Classes (PHP serializer only)
    |--------------------------------------------------------------------------
    |
    | Controls which class names may be instantiated when unserializing data.
    | Set to an array of class-string to allowlist specific classes, false to
    | disallow all objects, or null (default) to allow all classes.
    |
    | Restricting this value reduces the risk of PHP object-injection attacks
    | when the KV namespace is shared or accessible to untrusted writers.
    |
    */
    'allowed_classes' => null,

    'prefix' => env('CLOUDFLARE_KV_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Per-store overrides
    |--------------------------------------------------------------------------
    |
    | Every option above is a default. Any cache store using the 'cloudflare-kv'
    | driver may override them in config/cache.php to target a different KV
    | namespace or use a different serializer/prefix:
    |
    |   'stores' => [
    |       'kv-sessions' => [
    |           'driver'       => 'cloudflare-kv',
    |           'namespace_id' => env('CLOUDFLARE_KV_SESSIONS_NAMESPACE_ID'),
    |           'prefix'       => 'sess:',
    |       ],
    |   ],
    |
    | Overriding any of account_id, namespace_id, api_token, base_url, timeout or
    | connect_timeout gives that store its own client; otherwise it shares the
    | global one.
    |
    */
];
