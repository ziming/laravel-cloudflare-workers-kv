<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Commands;

use Illuminate\Console\Command;

final class LaravelCloudflareWorkersKvCommand extends Command
{
    public $signature = 'laravel-cloudflare-workers-kv';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
