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

];
