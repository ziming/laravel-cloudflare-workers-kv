<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

final readonly class RestCloudflareKvClient implements CloudflareKvClient
{
    public function __construct(
        private ClientInterface $http,
        private string $accountId,
        private string $namespaceId,
    ) {}

    public function get(string $key): ?string
    {
        try {
            $response = $this->send('GET', $this->valueUri($key));
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() === 404) {
                return null;
            }

            throw $exception;
        }

        return (string) $response->getBody();
    }

    public function put(string $key, string $value, ?int $seconds = null): bool
    {
        $uri = $this->valueUri($key);

        if ($seconds !== null) {
            $uri .= '?expiration_ttl='.(string) max(60, $seconds);
        }

        $response = $this->send('PUT', $uri, $value);

        return $this->successful($response);
    }

    public function delete(string $key): bool
    {
        $response = $this->send('DELETE', $this->valueUri($key));

        return $this->successful($response);
    }

    public function keys(string $prefix = ''): array
    {
        $keys = [];
        $cursor = null;

        do {
            $query = array_filter([
                'prefix' => $prefix === '' ? null : $prefix,
                'cursor' => $cursor,
            ]);

            $uri = $this->namespaceUri().'/keys'.($query === [] ? '' : '?'.http_build_query($query));
            $payload = json_decode((string) $this->send('GET', $uri)->getBody(), true);

            if (! is_array($payload) || ! isset($payload['result']) || ! is_array($payload['result'])) {
                throw new CloudflareKvException('Cloudflare KV returned an invalid key list response.');
            }

            foreach ($payload['result'] as $key) {
                if (is_array($key) && is_string($key['name'] ?? null)) {
                    $keys[] = $key['name'];
                }
            }

            $resultInfo = $payload['result_info'] ?? [];
            $cursor = is_array($resultInfo) && is_string($resultInfo['cursor'] ?? null) && $resultInfo['cursor'] !== ''
                ? $resultInfo['cursor']
                : null;
        } while ($cursor !== null);

        return $keys;
    }

    private function send(string $method, string $uri, ?string $body = null): ResponseInterface
    {
        try {
            return $this->http->send(new Request($method, $uri, [], $body));
        } catch (ClientException $exception) {
            throw $exception;
        } catch (GuzzleException $exception) {
            throw new CloudflareKvException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    private function valueUri(string $key): string
    {
        return $this->namespaceUri().'/values/'.rawurlencode($key);
    }

    private function namespaceUri(): string
    {
        return sprintf(
            'accounts/%s/storage/kv/namespaces/%s',
            rawurlencode($this->accountId),
            rawurlencode($this->namespaceId),
        );
    }

    private function successful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
