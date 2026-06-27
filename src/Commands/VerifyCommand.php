<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Commands;

use Throwable;
use Ziming\LaravelCloudflareWorkersKv\RestCloudflareKvClient;

final class VerifyCommand extends CloudflareKvCommand
{
    protected $signature = 'cloudflare-kv:verify {--store= : The cache store whose credentials to verify}';

    protected $description = 'Verify Cloudflare Workers KV credentials and connectivity.';

    public function handle(): int
    {
        try {
            $store = $this->resolveStore();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $client = $store->client();

        try {
            // A single bounded key list is the cheapest authenticated call.
            $client->keys($store->getPrefix(), 10);
        } catch (Throwable $exception) {
            $this->error('Failed to reach Cloudflare Workers KV: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Successfully connected to Cloudflare Workers KV.');
        $this->table(['Setting', 'Value'], [
            ['Account ID', $client instanceof RestCloudflareKvClient ? $client->accountId() : '(unknown)'],
            ['Namespace ID', $client instanceof RestCloudflareKvClient ? $client->namespaceId() : '(unknown)'],
            ['Serializer', $this->serializerName($store->serializer())],
            ['Prefix', $store->getPrefix() === '' ? '(none)' : $store->getPrefix()],
        ]);

        return self::SUCCESS;
    }
}
