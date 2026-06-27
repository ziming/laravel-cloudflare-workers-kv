<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use GuzzleHttp\Client;
use InvalidArgumentException;

final class CloudflareKvClientFactory
{
    private const string DEFAULT_BASE_URL = 'https://api.cloudflare.com/client/v4';

    private const float DEFAULT_TIMEOUT = 5.0;

    private const float DEFAULT_CONNECT_TIMEOUT = 2.0;

    /**
     * Build a REST client from a configuration array, validating that the
     * required credentials are present. Shared by the global singleton and by
     * per-store cache definitions so both go through the same validation.
     *
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): CloudflareKvClient
    {
        self::requireConfig($config, ['account_id', 'namespace_id', 'api_token']);

        return new RestCloudflareKvClient(
            new Client([
                'base_uri' => mb_rtrim((string) ($config['base_url'] ?? self::DEFAULT_BASE_URL), '/').'/',
                'headers' => [
                    'Authorization' => 'Bearer '.$config['api_token'],
                ],
                'http_errors' => true,
                'timeout' => (float) ($config['timeout'] ?? self::DEFAULT_TIMEOUT),
                'connect_timeout' => (float) ($config['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT),
            ]),
            (string) $config['account_id'],
            (string) $config['namespace_id'],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $keys
     */
    private static function requireConfig(array $config, array $keys): void
    {
        foreach ($keys as $key) {
            if (empty($config[$key])) {
                throw new InvalidArgumentException(
                    "Cloudflare KV configuration key [{$key}] is missing or empty. Check your cloudflare-workers-kv config.",
                );
            }
        }
    }
}
