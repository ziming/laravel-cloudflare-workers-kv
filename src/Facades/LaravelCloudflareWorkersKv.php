<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKv
 */
final class LaravelCloudflareWorkersKv extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKv::class;
    }
}
