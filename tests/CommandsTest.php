<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;
use Ziming\LaravelCloudflareWorkersKv\RestCloudflareKvClient;

/**
 * Bind a Cloudflare KV client backed by a Guzzle MockHandler as the shared
 * singleton, so cache stores without credential overrides resolve to it.
 */
function bindMockClient(array $responses): void
{
    $stack = HandlerStack::create(new MockHandler($responses));

    app()->instance(CloudflareKvClient::class, new RestCloudflareKvClient(
        new Client([
            'base_uri' => 'https://api.cloudflare.com/client/v4/',
            'handler' => $stack,
            'http_errors' => true,
        ]),
        'acct-123',
        'ns-456',
    ));
}

beforeEach(function (): void {
    config()->set('cache.stores.cloudflare', [
        'driver' => 'cloudflare-kv',
        'prefix' => 'app:',
    ]);
});

// ──────────────────────────────────────────────
// cloudflare-kv:verify
// ──────────────────────────────────────────────

it('verifies connectivity and reports the resolved configuration', function (): void {
    bindMockClient([
        new Response(200, [], json_encode([
            'result' => [],
            'result_info' => ['cursor' => ''],
        ], JSON_THROW_ON_ERROR)),
    ]);

    $this->artisan('cloudflare-kv:verify', ['--store' => 'cloudflare'])
        ->expectsOutputToContain('Successfully connected to Cloudflare Workers KV.')
        ->expectsOutputToContain('acct-123')
        ->expectsOutputToContain('ns-456')
        ->assertSuccessful();
});

it('fails verification when the credentials are rejected', function (): void {
    bindMockClient([new Response(403, [], '{"success":false}')]);

    $this->artisan('cloudflare-kv:verify', ['--store' => 'cloudflare'])
        ->expectsOutputToContain('Failed to reach Cloudflare Workers KV')
        ->assertFailed();
});

it('fails verification for a store that is not a Cloudflare KV store', function (): void {
    config()->set('cache.stores.array-store', ['driver' => 'array']);

    $this->artisan('cloudflare-kv:verify', ['--store' => 'array-store'])
        ->assertFailed();
});

// ──────────────────────────────────────────────
// cloudflare-kv:keys
// ──────────────────────────────────────────────

it('lists keys for a store', function (): void {
    bindMockClient([
        new Response(200, [], json_encode([
            'result' => [['name' => 'app:one'], ['name' => 'app:two']],
            'result_info' => ['cursor' => ''],
        ], JSON_THROW_ON_ERROR)),
    ]);

    $this->artisan('cloudflare-kv:keys', ['--store' => 'cloudflare'])
        ->expectsOutputToContain('app:one')
        ->expectsOutputToContain('app:two')
        ->expectsOutputToContain('2 key(s) found.')
        ->assertSuccessful();
});

it('reports when no keys are found', function (): void {
    bindMockClient([
        new Response(200, [], json_encode([
            'result' => [],
            'result_info' => ['cursor' => ''],
        ], JSON_THROW_ON_ERROR)),
    ]);

    $this->artisan('cloudflare-kv:keys', ['--store' => 'cloudflare'])
        ->expectsOutputToContain('No keys found.')
        ->assertSuccessful();
});

// ──────────────────────────────────────────────
// cloudflare-kv:get
// ──────────────────────────────────────────────

it('gets and deserializes a value', function (): void {
    bindMockClient([new Response(200, [], serialize(['name' => 'Ada']))]);

    $this->artisan('cloudflare-kv:get', ['key' => 'user:1', '--store' => 'cloudflare'])
        ->expectsOutputToContain('Ada')
        ->assertSuccessful();
});

it('gets the raw stored bytes with --raw', function (): void {
    bindMockClient([new Response(200, [], serialize(['name' => 'Ada']))]);

    $this->artisan('cloudflare-kv:get', ['key' => 'user:1', '--store' => 'cloudflare', '--raw' => true])
        ->expectsOutputToContain(serialize(['name' => 'Ada']))
        ->assertSuccessful();
});

it('reports a missing key', function (): void {
    bindMockClient([new Response(404, [], '{"success":false}')]);

    $this->artisan('cloudflare-kv:get', ['key' => 'missing', '--store' => 'cloudflare'])
        ->expectsOutputToContain('not found')
        ->assertFailed();
});
