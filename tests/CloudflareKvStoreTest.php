<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvClient;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvException;
use Ziming\LaravelCloudflareWorkersKv\LaravelCloudflareWorkersKv;
use Ziming\LaravelCloudflareWorkersKv\RestCloudflareKvClient;
use Ziming\LaravelCloudflareWorkersKv\Tests\Fakes\InMemoryCloudflareKvClient;
use Ziming\LaravelCloudflareWorkersKv\Tests\Fakes\ThrowingCloudflareKvClient;

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
        ->and($this->client->ttls['app:user:1'])->toBe(120);
});

it('resolves an absolute expiry from the TTL on put', function (): void {
    $before = time();
    Cache::store('cloudflare')->put('key', 'val', 300);
    $after = time();

    $expiry = $this->client->expirations['app:key'];
    expect($expiry)->toBeGreaterThanOrEqual($before + 300)
        ->and($expiry)->toBeLessThanOrEqual($after + 300);
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
// Deserialize failures degrade to a miss
// ──────────────────────────────────────────────

it('treats a value that fails to deserialize as a cache miss', function (): void {
    $this->client->values['shared:corrupt'] = '{not valid json';

    expect(Cache::store('cloudflare-json')->get('corrupt'))->toBeNull();
});

it('treats a corrupt value within many() as a miss', function (): void {
    Cache::store('cloudflare-json')->put('good', ['ok' => true], 60);
    $this->client->values['shared:corrupt'] = '{not valid json';

    expect(Cache::store('cloudflare-json')->many(['good', 'corrupt']))->toBe([
        'good' => ['ok' => true],
        'corrupt' => null,
    ]);
});

// ──────────────────────────────────────────────
// Graceful reads (config-gated fail-open)
// ──────────────────────────────────────────────

it('degrades reads to a miss when graceful is enabled', function (): void {
    app()->instance(CloudflareKvClient::class, new ThrowingCloudflareKvClient());

    config()->set('cache.stores.kv-graceful', [
        'driver' => 'cloudflare-kv',
        'graceful' => true,
    ]);

    expect(Cache::store('kv-graceful')->get('x'))->toBeNull()
        ->and(Cache::store('kv-graceful')->many(['x', 'y']))->toBe(['x' => null, 'y' => null]);
});

it('rethrows read failures by default', function (): void {
    app()->instance(CloudflareKvClient::class, new ThrowingCloudflareKvClient());

    config()->set('cache.stores.kv-loud', ['driver' => 'cloudflare-kv']);

    expect(fn () => Cache::store('kv-loud')->get('x'))->toThrow(CloudflareKvException::class);
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
        ->and(Cache::store('cloudflare')->get('y'))->toBe(20)
        ->and($this->client->ttls['app:x'])->toBe(120);
});

// ──────────────────────────────────────────────
// increment / decrement — native absolute expiry
// ──────────────────────────────────────────────

it('increments a numeric cache value', function (): void {
    Cache::store('cloudflare')->put('counter', 5, 300);

    $result = Cache::store('cloudflare')->increment('counter');

    expect($result)->toBe(6)
        ->and(Cache::store('cloudflare')->get('counter'))->toBe(6);
});

it('preserves the exact absolute expiry across increments (no creep)', function (): void {
    Cache::store('cloudflare')->put('counter', 0, 300);
    $expiry = $this->client->expirations['app:counter'];

    Cache::store('cloudflare')->increment('counter');
    Cache::store('cloudflare')->increment('counter');

    expect(Cache::store('cloudflare')->get('counter'))->toBe(2)
        ->and($this->client->expirations['app:counter'])->toBe($expiry);
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

it('returns false when incrementing an already-expired key', function (): void {
    $this->client->values['app:counter'] = serialize(5);
    $this->client->expirations['app:counter'] = time() - 10;

    expect(Cache::store('cloudflare')->increment('counter'))->toBeFalse();
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

    $result = Cache::store('cloudflare')->touch('key', 300);

    expect($result)->toBeTrue()
        ->and($this->client->ttls['app:key'])->toBe(300);
});

it('touch returns false for a missing key', function (): void {
    expect(Cache::store('cloudflare')->touch('missing', 60))->toBeFalse();
});

// ──────────────────────────────────────────────
// Per-store credentials (multi-namespace)
// ──────────────────────────────────────────────

it('routes per-store credential overrides to distinct namespaces', function (): void {
    config()->set('cloudflare-workers-kv.account_id', 'acct');
    config()->set('cloudflare-workers-kv.api_token', 'token');

    config()->set('cache.stores.kv-a', [
        'driver' => 'cloudflare-kv',
        'namespace_id' => 'namespace-a',
    ]);
    config()->set('cache.stores.kv-b', [
        'driver' => 'cloudflare-kv',
        'namespace_id' => 'namespace-b',
    ]);

    $clientA = Cache::store('kv-a')->getStore()->client();
    $clientB = Cache::store('kv-b')->getStore()->client();

    expect($clientA)->toBeInstanceOf(RestCloudflareKvClient::class)
        ->and($clientB)->toBeInstanceOf(RestCloudflareKvClient::class)
        ->and($clientA->namespaceId())->toBe('namespace-a')
        ->and($clientB->namespaceId())->toBe('namespace-b')
        ->and($clientA->accountId())->toBe('acct');
});

it('reuses the shared client when a store does not override credentials', function (): void {
    expect(Cache::store('cloudflare')->getStore()->client())->toBe($this->client);
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

it('stores values forever through the direct client', function (): void {
    $kv = LaravelCloudflareWorkersKv::json($this->client);

    $kv->forever('flag', true);

    expect($this->client->values['flag'])->toBe('true')
        ->and($this->client->expirations['flag'])->toBeNull();
});

it('exposes value, expiration and metadata through the direct client', function (): void {
    $kv = LaravelCloudflareWorkersKv::serialized($this->client, 'p:');
    $kv->put('answer', 42, 300);
    $expiry = $this->client->expirations['p:answer'];

    expect($kv->getWithMetadata('answer'))->toBe([
        'value' => 42,
        'expiration' => $expiry,
        'metadata' => [],
    ])
        ->and($kv->expiresAt('answer'))->toBe($expiry)
        ->and($kv->getWithMetadata('missing'))->toBeNull()
        ->and($kv->expiresAt('missing'))->toBeNull();
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
