<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;
use Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKv;
use Ziming\LaravelCloudflareWorkersKv\Tests\Fakes\InMemoryCloudflareKvClient;

beforeEach(function (): void {
    $this->client = new InMemoryCloudflareKvClient();

    app()->instance(CloudflareKvClient::class, $this->client);
});

it('stores Laravel cache values with PHP serialization by default', function (): void {
    config()->set('cache.stores.cloudflare', [
        'driver' => 'cloudflare-kv',
        'prefix' => 'app:',
    ]);

    Cache::store('cloudflare')->put('user:1', ['name' => 'Ada'], 120);

    expect($this->client->values['app:user:1'])->toBe(serialize(['name' => 'Ada']))
        ->and(Cache::store('cloudflare')->get('user:1'))->toBe(['name' => 'Ada'])
        ->and($this->client->expirations['app:user:1'])->toBe(120);
});

it('can store JSON values for Worker interoperability', function (): void {
    config()->set('cache.stores.cloudflare-json', [
        'driver' => 'cloudflare-kv',
        'serializer' => 'json',
        'prefix' => 'shared:',
    ]);

    Cache::store('cloudflare-json')->forever('feature-flags', [
        'checkout' => true,
        'limit' => 5,
    ]);

    expect($this->client->values['shared:feature-flags'])->toBe('{"checkout":true,"limit":5}')
        ->and(Cache::store('cloudflare-json')->get('feature-flags'))->toBe([
            'checkout' => true,
            'limit' => 5,
        ]);
});

it('flushes only keys with the configured prefix', function (): void {
    config()->set('cache.stores.cloudflare', [
        'driver' => 'cloudflare-kv',
        'prefix' => 'app:',
    ]);

    $this->client->put('app:one', '1');
    $this->client->put('other:two', '2');

    Cache::store('cloudflare')->flush();

    expect($this->client->values)->toHaveKey('other:two')
        ->and($this->client->values)->not->toHaveKey('app:one');
});

it('supports direct JSON and serialized access helpers', function (): void {
    LaravelCloudflareWorkersKv::json($this->client)->put('worker-key', ['value' => 10]);
    LaravelCloudflareWorkersKv::serialized($this->client)->put('laravel-key', ['value' => 20]);

    expect($this->client->values['worker-key'])->toBe('{"value":10}')
        ->and($this->client->values['laravel-key'])->toBe(serialize(['value' => 20]))
        ->and(LaravelCloudflareWorkersKv::json($this->client)->get('worker-key'))->toBe(['value' => 10])
        ->and(LaravelCloudflareWorkersKv::serialized($this->client)->get('laravel-key'))->toBe(['value' => 20]);
});
