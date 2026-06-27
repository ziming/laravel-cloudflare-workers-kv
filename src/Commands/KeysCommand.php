<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Commands;

use Throwable;

final class KeysCommand extends CloudflareKvCommand
{
    protected $signature = 'cloudflare-kv:keys
        {--prefix= : Only list keys starting with this prefix (in addition to the store prefix)}
        {--store= : The cache store to inspect}';

    protected $description = 'List keys stored in a Cloudflare Workers KV namespace.';

    public function handle(): int
    {
        try {
            $store = $this->resolveStore();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $option = $this->option('prefix');
        $prefix = $store->getPrefix().(is_string($option) ? $option : '');

        try {
            $keys = $store->client()->keys($prefix);
        } catch (Throwable $exception) {
            $this->error('Failed to list keys: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($keys === []) {
            $this->info('No keys found.');

            return self::SUCCESS;
        }

        foreach ($keys as $key) {
            $this->line($key);
        }

        $this->info(sprintf('%d key(s) found.', count($keys)));

        return self::SUCCESS;
    }
}
