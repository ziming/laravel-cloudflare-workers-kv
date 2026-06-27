<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Commands;

use Throwable;

final class GetCommand extends CloudflareKvCommand
{
    protected $signature = 'cloudflare-kv:get
        {key : The cache key to read (without the store prefix)}
        {--store= : The cache store to read from}
        {--raw : Output the raw stored bytes instead of the deserialized value}';

    protected $description = 'Fetch and display a single value from Cloudflare Workers KV.';

    public function handle(): int
    {
        try {
            $store = $this->resolveStore();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $key = (string) $this->argument('key');

        try {
            $value = $this->option('raw')
                ? $store->client()->get($store->getPrefix().$key)
                : $store->get($key);
        } catch (Throwable $exception) {
            $this->error('Failed to read key: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($value === null) {
            $this->warn(sprintf('Key [%s] not found.', $key));

            return self::FAILURE;
        }

        $this->line($this->option('raw') && is_string($value) ? $value : var_export($value, true));

        return self::SUCCESS;
    }
}
