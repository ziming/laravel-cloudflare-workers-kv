<?php

declare(strict_types=1);

// config for Ziming/LaravelCloudflareWorkersKv
return [
    'account_id' => $_ENV['CLOUDFLARE_KV_ACCOUNT_ID'] ?? $_SERVER['CLOUDFLARE_KV_ACCOUNT_ID'] ?? null,

    'namespace_id' => $_ENV['CLOUDFLARE_KV_NAMESPACE_ID'] ?? $_SERVER['CLOUDFLARE_KV_NAMESPACE_ID'] ?? null,

    'api_token' => $_ENV['CLOUDFLARE_KV_API_TOKEN'] ?? $_SERVER['CLOUDFLARE_KV_API_TOKEN'] ?? null,

    'base_url' => $_ENV['CLOUDFLARE_KV_BASE_URL'] ?? $_SERVER['CLOUDFLARE_KV_BASE_URL'] ?? 'https://api.cloudflare.com/client/v4',

    'serializer' => $_ENV['CLOUDFLARE_KV_SERIALIZER'] ?? $_SERVER['CLOUDFLARE_KV_SERIALIZER'] ?? 'php',

    'prefix' => $_ENV['CLOUDFLARE_KV_PREFIX'] ?? $_SERVER['CLOUDFLARE_KV_PREFIX'] ?? '',

];
