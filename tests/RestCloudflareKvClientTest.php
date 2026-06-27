<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Ziming\LaravelCloudflareWorkersKv\CloudflareKvException;
use Ziming\LaravelCloudflareWorkersKv\RestCloudflareKvClient;

function makeClient(array $responses, array &$history = []): RestCloudflareKvClient
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    return new RestCloudflareKvClient(
        new Client([
            'base_uri' => 'https://api.cloudflare.com/client/v4/',
            'handler' => $stack,
        ]),
        'account-id',
        'namespace-id',
    );
}

// ──────────────────────────────────────────────
// Identity accessors
// ──────────────────────────────────────────────

it('exposes the configured account and namespace', function (): void {
    $client = makeClient([]);

    expect($client->accountId())->toBe('account-id')
        ->and($client->namespaceId())->toBe('namespace-id');
});

// ──────────────────────────────────────────────
// Single-value PUT (multipart)
// ──────────────────────────────────────────────

it('writes a value to the Cloudflare values endpoint as multipart/form-data', function (): void {
    $history = [];
    $client = makeClient([new Response(200, [], '{"success":true}')], $history);

    $client->put('feature:checkout', '{"enabled":true}', 300);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('PUT')
        ->and((string) $request->getUri())->toContain('/values/feature%3Acheckout?expiration_ttl=300')
        ->and($request->getHeaderLine('Content-Type'))->toContain('multipart/form-data')
        ->and((string) $request->getBody())->toContain('{"enabled":true}');
});

it('clamps expiration_ttl to the 60-second minimum', function (): void {
    $history = [];
    $client = makeClient([new Response(200, [], '{"success":true}')], $history);

    $client->put('key', 'value', 30);

    expect((string) $history[0]['request']->getUri())->toContain('expiration_ttl=60');
});

it('writes an absolute expiration when one is provided', function (): void {
    $history = [];
    $client = makeClient([new Response(200, [], '{"success":true}')], $history);

    $expiration = time() + 3600;
    $client->put('key', 'value', null, $expiration);

    $uri = (string) $history[0]['request']->getUri();
    expect($uri)->toContain('expiration='.$expiration)
        ->and($uri)->not->toContain('expiration_ttl');
});

it('clamps an absolute expiration to the 60-second floor', function (): void {
    $history = [];
    $client = makeClient([new Response(200, [], '{"success":true}')], $history);

    $client->put('key', 'value', null, time() + 5);

    preg_match('/expiration=(\d+)/', (string) $history[0]['request']->getUri(), $matches);
    expect((int) $matches[1])->toBeGreaterThanOrEqual(time() + 59);
});

it('writes a value with no expiry when ttl and expiration are null', function (): void {
    $history = [];
    $client = makeClient([new Response(200, [], '{"success":true}')], $history);

    $client->put('key', 'value');

    expect((string) $history[0]['request']->getUri())->not->toContain('expiration');
});

it('returns null for a missing key on get', function (): void {
    $client = makeClient([new Response(404, [], '{"success":false}')]);

    expect($client->get('missing'))->toBeNull();
});

it('wraps non-404 client errors in CloudflareKvException on get', function (): void {
    $client = makeClient([new Response(403, [], '{"success":false}')]);

    expect(fn () => $client->get('key'))->toThrow(CloudflareKvException::class);
});

// ──────────────────────────────────────────────
// Bulk GET
// ──────────────────────────────────────────────

it('retrieves multiple values via the bulk get endpoint as text', function (): void {
    $history = [];
    $client = makeClient([
        new Response(200, [], json_encode([
            'success' => true,
            'result' => ['values' => ['app:one' => 'v1', 'app:two' => 'v2']],
        ], JSON_THROW_ON_ERROR)),
    ], $history);

    $result = $client->many(['app:one', 'app:two', 'app:missing']);

    expect($result)->toBe(['app:one' => 'v1', 'app:two' => 'v2'])
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toContain('/bulk/get');

    $body = json_decode((string) $history[0]['request']->getBody(), true);
    expect($body['keys'])->toContain('app:one')
        ->and($body['keys'])->toContain('app:two')
        ->and($body['keys'])->toContain('app:missing')
        ->and($body['type'])->toBe('text');
});

// ──────────────────────────────────────────────
// getWithMetadata (bulk get + withMetadata)
// ──────────────────────────────────────────────

it('reads a value with its expiration and metadata via bulk get', function (): void {
    $history = [];
    $client = makeClient([
        new Response(200, [], json_encode([
            'success' => true,
            'result' => ['values' => [
                'my:key' => ['value' => '{"n":1}', 'expiration' => 9999999, 'metadata' => ['x' => 'y']],
            ]],
        ], JSON_THROW_ON_ERROR)),
    ], $history);

    $entry = $client->getWithMetadata('my:key');

    expect($entry)->toBe(['value' => '{"n":1}', 'expiration' => 9999999, 'metadata' => ['x' => 'y']])
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toContain('/bulk/get');

    $body = json_decode((string) $history[0]['request']->getBody(), true);
    expect($body['keys'])->toBe(['my:key'])
        ->and($body['type'])->toBe('text')
        ->and($body['withMetadata'])->toBeTrue();
});

it('returns null from getWithMetadata for a missing key', function (): void {
    $client = makeClient([
        new Response(200, [], json_encode([
            'success' => true,
            'result' => ['values' => []],
        ], JSON_THROW_ON_ERROR)),
    ]);

    expect($client->getWithMetadata('missing'))->toBeNull();
});

it('returns a null expiration from getWithMetadata when the key never expires', function (): void {
    $client = makeClient([
        new Response(200, [], json_encode([
            'success' => true,
            'result' => ['values' => [
                'my:key' => ['value' => 'v', 'metadata' => null],
            ]],
        ], JSON_THROW_ON_ERROR)),
    ]);

    expect($client->getWithMetadata('my:key'))->toBe([
        'value' => 'v',
        'expiration' => null,
        'metadata' => [],
    ]);
});

// ──────────────────────────────────────────────
// Bulk PUT
// ──────────────────────────────────────────────

it('writes multiple values via the bulk write endpoint', function (): void {
    $history = [];
    $client = makeClient([
        new Response(200, [], json_encode([
            'success' => true,
            'result' => ['successful_key_count' => 2, 'unsuccessful_keys' => []],
        ], JSON_THROW_ON_ERROR)),
    ], $history);

    $client->putMany([
        ['key' => 'k1', 'value' => 'v1', 'seconds' => 120, 'base64' => false],
        ['key' => 'k2', 'value' => 'v2'],
    ]);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('PUT')
        ->and((string) $request->getUri())->toContain('/bulk')
        ->and($request->getHeaderLine('Content-Type'))->toContain('application/json');

    $body = json_decode((string) $request->getBody(), true);
    expect($body[0]['key'])->toBe('k1')
        ->and($body[0]['expiration_ttl'])->toBe(120)
        ->and($body[1]['key'])->toBe('k2');
});

it('base64-encodes values flagged as binary in bulk writes', function (): void {
    $history = [];
    $client = makeClient([
        new Response(200, [], json_encode([
            'success' => true,
            'result' => ['successful_key_count' => 1, 'unsuccessful_keys' => []],
        ], JSON_THROW_ON_ERROR)),
    ], $history);

    $raw = serialize(['data' => 'abc']);
    $client->putMany([['key' => 'k', 'value' => $raw, 'base64' => true]]);

    $body = json_decode((string) $history[0]['request']->getBody(), true);
    expect($body[0]['base64'])->toBeTrue()
        ->and($body[0]['value'])->toBe(base64_encode($raw));
});

// ──────────────────────────────────────────────
// Bulk DELETE
// ──────────────────────────────────────────────

it('deletes multiple keys via the bulk delete endpoint', function (): void {
    $history = [];
    $client = makeClient([
        new Response(200, [], json_encode([
            'success' => true,
            'result' => ['successful_key_count' => 2, 'unsuccessful_keys' => []],
        ], JSON_THROW_ON_ERROR)),
    ], $history);

    $client->deleteMany(['k1', 'k2']);

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toContain('/bulk/delete');

    $body = json_decode((string) $request->getBody(), true);
    expect($body)->toBe(['k1', 'k2']);
});

// ──────────────────────────────────────────────
// Key listing (cursor pagination)
// ──────────────────────────────────────────────

it('lists keys across Cloudflare cursor pages', function (): void {
    $history = [];
    $client = makeClient([
        new Response(200, [], json_encode([
            'result' => [['name' => 'app:one']],
            'result_info' => ['cursor' => 'next-page'],
        ], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode([
            'result' => [['name' => 'app:two']],
            'result_info' => ['cursor' => ''],
        ], JSON_THROW_ON_ERROR)),
    ], $history);

    expect($client->keys('app:'))->toBe(['app:one', 'app:two'])
        ->and((string) $history[0]['request']->getUri())->toContain('prefix=app%3A')
        ->and((string) $history[1]['request']->getUri())->toContain('cursor=next-page');
});

it('lists a single bounded page without paginating', function (): void {
    $history = [];
    $client = makeClient([
        new Response(200, [], json_encode([
            'result' => [['name' => 'app:one']],
            'result_info' => ['cursor' => 'more-pages'],
        ], JSON_THROW_ON_ERROR)),
    ], $history);

    expect($client->keys('app:', 10))->toBe(['app:one'])
        ->and($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toContain('limit=10');
});
