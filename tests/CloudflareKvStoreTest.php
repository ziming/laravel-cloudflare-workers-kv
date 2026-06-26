<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;
use Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKv;
use Ziming\LaravelCloudflareWorkersKv\Tests\Fakes\InMemoryCloudflareKvClient;

beforeEach(function (): void {
    $this->client = new InMemoryCloudflareKvClient();
    app()->instance(CloudflareKvClient::class, $this->client);

    config()->set('cache.stores.cloudflare', [
        'driver' => 'cloudflare-kv',
        'prefix' => 'app:',
    ]);

    config()->set('cache.stores.cloudflare-json', [
        'driver' => 'cloudflare-kv',
        'serializer' => 'json',
        'prefix' => 'shared:',
    ]);
});

// ──────────────────────────────────────────────
// Basic put/get
// ──────────────────────────────────────────────

it('stores Laravel cache values with PHP serialization by default', function (): void {
    Cache::store('cloudflare')->put('user:1', ['name' => 'Ada'], 120);

    expect($this->client->values['app:user:1'])->toBe(serialize(['name' => 'Ada']))
        ->and(Cache::store('cloudflare')->get('user:1'))->toBe(['name' => 'Ada'])
        ->and($this->client->expirations['app:user:1'])->toBe(120);
});

it('stores the expiry timestamp in metadata on put', function (): void {
    $before = time();
    Cache::store('cloudflare')->put('key', 'val', 300);
    $after = time();

    $meta = $this->client->metadata['app:key'];
    expect($meta['e'])->toBeGreaterThanOrEqual($before + 300)
        ->and($meta['e'])->toBeLessThanOrEqual($after + 300);
});

it('can store JSON values for Worker interoperability', function (): void {
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

it('returns null for a missing key', function (): void {
    expect(Cache::store('cloudflare')->get('missing'))->toBeNull();
});

it('deletes a key when put is called with zero or negative seconds', function (): void {
    $this->client->put('app:key', 'value');

    Cache::store('cloudflare')->put('key', 'value', 0);

    expect($this->client->values)->not->toHaveKey('app:key');
});

// ──────────────────────────────────────────────
// many / putMany
// ──────────────────────────────────────────────

it('retrieves many keys at once, returning null for missing keys', function (): void {
    Cache::store('cloudflare')->put('a', 1, 60);
    Cache::store('cloudflare')->put('b', 2, 60);

    $result = Cache::store('cloudflare')->many(['a', 'b', 'c']);

    expect($result)->toBe(['a' => 1, 'b' => 2, 'c' => null]);
});

it('stores many values in one bulk call with a shared TTL', function (): void {
    Cache::store('cloudflare')->putMany(['x' => 10, 'y' => 20], 120);

    expect(Cache::store('cloudflare')->get('x'))->toBe(10)
        ->and(Cache::store('cloudflare')->get('y'))->toBe(20);
});

// ──────────────────────────────────────────────
// increment / decrement — TTL preservation
// ──────────────────────────────────────────────

it('increments a numeric cache value', function (): void {
    Cache::store('cloudflare')->put('counter', 5, 300);

    $result = Cache::store('cloudflare')->increment('counter');

    expect($result)->toBe(6)
        ->and(Cache::store('cloudflare')->get('counter'))->toBe(6);
});

it('preserves the remaining TTL when incrementing', function (): void {
    Cache::store('cloudflare')->put('counter', 0, 300);

    Cache::store('cloudflare')->increment('counter');

    // The re-written value should still carry an expiration (not null = forever)
    expect($this->client->expirations['app:counter'])->not->toBeNull();
});

it('decrements a numeric cache value', function (): void {
    Cache::store('cloudflare')->put('counter', 10, 300);

    $result = Cache::store('cloudflare')->decrement('counter', 3);

    expect($result)->toBe(7);
});

it('returns false when incrementing a non-numeric value', function (): void {
    Cache::store('cloudflare')->put('name', 'Ada', 300);

    expect(Cache::store('cloudflare')->increment('name'))->toBeFalse();
});

it('returns false when incrementing a missing key', function (): void {
    expect(Cache::store('cloudflare')->increment('missing'))->toBeFalse();
});

it('increments by a custom step', function (): void {
    Cache::store('cloudflare')->put('hits', 0, 300);

    expect(Cache::store('cloudflare')->increment('hits', 5))->toBe(5);
});

it('increments a key stored forever (no TTL), keeping it forever', function (): void {
    Cache::store('cloudflare')->forever('counter', 0);

    $result = Cache::store('cloudflare')->increment('counter');

    expect($result)->toBe(1)
        ->and($this->client->expirations['app:counter'])->toBeNull();
});

// ──────────────────────────────────────────────
// forget / flush
// ──────────────────────────────────────────────

it('forgets a single key', function (): void {
    Cache::store('cloudflare')->put('key', 'value', 60);

    Cache::store('cloudflare')->forget('key');

    expect($this->client->values)->not->toHaveKey('app:key');
});

it('flushes only keys with the configured prefix', function (): void {
    $this->client->put('app:one', '1');
    $this->client->put('other:two', '2');

    Cache::store('cloudflare')->flush();

    expect($this->client->values)->toHaveKey('other:two')
        ->and($this->client->values)->not->toHaveKey('app:one');
});

// ──────────────────────────────────────────────
// touch
// ──────────────────────────────────────────────

it('touch refreshes the TTL of an existing key', function (): void {
    $this->client->put('app:key', serialize('hello'), 60);
    $this->client->metadata['app:key'] = ['e' => time() + 60];

    $result = Cache::store('cloudflare')->touch('key', 300);

    expect($result)->toBeTrue()
        ->and($this->client->expirations['app:key'])->toBe(300);
});

it('touch returns false for a missing key', function (): void {
    expect(Cache::store('cloudflare')->touch('missing', 60))->toBeFalse();
});

// ──────────────────────────────────────────────
// Key validation
// ──────────────────────────────────────────────

it('rejects an empty key', function (): void {
    Cache::store('cloudflare')->get('');
})->throws(InvalidArgumentException::class, 'empty');

it('rejects a key containing whitespace', function (): void {
    Cache::store('cloudflare')->get('bad key');
})->throws(InvalidArgumentException::class, 'whitespace');

it('rejects a key that exceeds 512 bytes when combined with the prefix', function (): void {
    Cache::store('cloudflare')->get(str_repeat('a', 510));
})->throws(InvalidArgumentException::class, '512-byte');

// ──────────────────────────────────────────────
// Direct client helpers
// ──────────────────────────────────────────────

it('supports direct JSON and serialized access helpers', function (): void {
    LaravelCloudflareWorkersKv::json($this->client)->put('worker-key', ['value' => 10]);
    LaravelCloudflareWorkersKv::serialized($this->client)->put('laravel-key', ['value' => 20]);

    expect($this->client->values['worker-key'])->toBe('{"value":10}')
        ->and($this->client->values['laravel-key'])->toBe(serialize(['value' => 20]))
        ->and(LaravelCloudflareWorkersKv::json($this->client)->get('worker-key'))->toBe(['value' => 10])
        ->and(LaravelCloudflareWorkersKv::serialized($this->client)->get('laravel-key'))->toBe(['value' => 20]);
});

it('supports bulk helpers on the direct client', function (): void {
    $kv = LaravelCloudflareWorkersKv::json($this->client, 'shared:');

    $kv->putMany(['a' => 1, 'b' => 2], 3600);

    expect($kv->many(['a', 'b', 'c']))->toBe(['a' => 1, 'b' => 2, 'c' => null])
        ->and($this->client->values)->toHaveKey('shared:a');

    $kv->deleteMany(['a', 'b']);

    expect($this->client->values)->not->toHaveKey('shared:a')
        ->and($this->client->values)->not->toHaveKey('shared:b');
});
