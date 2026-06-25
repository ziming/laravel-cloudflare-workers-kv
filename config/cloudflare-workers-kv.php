<?php

declare(strict_types=1);

// config for Ziming/LaravelCloudflareWorkersKv
return [
    'account_id' => env('CLOUDFLARE_KV_ACCOUNT_ID'),

    'namespace_id' => env('CLOUDFLARE_KV_NAMESPACE_ID'),

    'api_token' => env('CLOUDFLARE_KV_API_TOKEN'),

    'base_url' => env('CLOUDFLARE_KV_BASE_URL', 'https://api.cloudflare.com/client/v4'),

    'serializer' => env('CLOUDFLARE_KV_SERIALIZER', 'php'),

    'prefix' => env('CLOUDFLARE_KV_PREFIX', ''),

];
