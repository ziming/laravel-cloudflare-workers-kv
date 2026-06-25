<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Ziming\LaravelCloudflareWorkersKv\RestCloudflareKvClient;

it('writes raw values to the Cloudflare values endpoint', function (): void {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], '{"success":true}'),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    $client = new RestCloudflareKvClient(
        new Client([
            'base_uri' => 'https://api.cloudflare.com/client/v4/',
            'handler' => $stack,
        ]),
        'account-id',
        'namespace-id',
    );

    $client->put('shared:feature flags', '{"checkout":true}', 30);

    expect($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toBe('https://api.cloudflare.com/client/v4/accounts/account-id/storage/kv/namespaces/namespace-id/values/shared%3Afeature%20flags?expiration_ttl=60')
        ->and($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getBody())->toBe('{"checkout":true}');
});

it('lists keys across Cloudflare cursor pages', function (): void {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'result' => [
                ['name' => 'app:one'],
            ],
            'result_info' => ['cursor' => 'next-page'],
        ], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode([
            'result' => [
                ['name' => 'app:two'],
            ],
            'result_info' => ['cursor' => ''],
        ], JSON_THROW_ON_ERROR)),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    $client = new RestCloudflareKvClient(
        new Client([
            'base_uri' => 'https://api.cloudflare.com/client/v4/',
            'handler' => $stack,
        ]),
        'account-id',
        'namespace-id',
    );

    expect($client->keys('app:'))->toBe(['app:one', 'app:two'])
        ->and((string) $history[0]['request']->getUri())->toBe('https://api.cloudflare.com/client/v4/accounts/account-id/storage/kv/namespaces/namespace-id/keys?prefix=app%3A')
        ->and((string) $history[1]['request']->getUri())->toBe('https://api.cloudflare.com/client/v4/accounts/account-id/storage/kv/namespaces/namespace-id/keys?prefix=app%3A&cursor=next-page');
});
