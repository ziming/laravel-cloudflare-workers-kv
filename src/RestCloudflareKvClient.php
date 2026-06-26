<?php

declare(strict_types=1);

namespace Ziming\LaravelCloudflareWorkersKv;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

final readonly class RestCloudflareKvClient implements CloudflareKvClient
{
    private const int BULK_GET_LIMIT = 100;

    private const int BULK_WRITE_LIMIT = 10_000;

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

            throw new CloudflareKvException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }

        return (string) $response->getBody();
    }

    public function getMetadata(string $key): ?array
    {
        try {
            $response = $this->send('GET', $this->metadataUri($key));
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() === 404) {
                return null;
            }

            throw new CloudflareKvException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (! is_array($payload) || ! isset($payload['result'])) {
            return null;
        }

        return is_array($payload['result']) ? $payload['result'] : null;
    }

    public function many(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $results = [];

        foreach (array_chunk($keys, self::BULK_GET_LIMIT) as $chunk) {
            $body = json_encode(['keys' => $chunk], JSON_THROW_ON_ERROR);
            $response = $this->send('POST', $this->namespaceUri().'/bulk/get', $body, ['Content-Type' => 'application/json']);
            $payload = json_decode((string) $response->getBody(), true);

            if (! is_array($payload) || ! isset($payload['result']['values']) || ! is_array($payload['result']['values'])) {
                throw new CloudflareKvException('Cloudflare KV returned an invalid bulk get response.');
            }

            foreach ($payload['result']['values'] as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $results[$k] = $v;
                }
            }
        }

        return $results;
    }

    public function put(string $key, string $value, ?int $seconds = null, array $metadata = []): bool
    {
        $uri = $this->valueUri($key);

        if ($seconds !== null) {
            $uri .= '?expiration_ttl='.(string) max(60, $seconds);
        }

        $parts = [['name' => 'value', 'contents' => $value]];

        if ($metadata !== []) {
            $parts[] = ['name' => 'metadata', 'contents' => json_encode($metadata, JSON_THROW_ON_ERROR)];
        }

        $multipart = new MultipartStream($parts);
        $response = $this->send(
            'PUT',
            $uri,
            $multipart,
            ['Content-Type' => 'multipart/form-data; boundary='.$multipart->getBoundary()],
        );

        return $this->isSuccessful($response);
    }

    public function putMany(array $entries): bool
    {
        if ($entries === []) {
            return true;
        }

        $success = true;

        foreach (array_chunk($entries, self::BULK_WRITE_LIMIT) as $chunk) {
            $body = json_encode(array_map(static function (array $entry): array {
                $item = [
                    'key' => $entry['key'],
                    'value' => $entry['base64'] ?? false ? base64_encode($entry['value']) : $entry['value'],
                    'base64' => $entry['base64'] ?? false,
                ];

                if (isset($entry['seconds'])) {
                    $item['expiration_ttl'] = max(60, $entry['seconds']);
                }

                if (isset($entry['metadata']) && $entry['metadata'] !== []) {
                    $item['metadata'] = $entry['metadata'];
                }

                return $item;
            }, $chunk), JSON_THROW_ON_ERROR);

            $response = $this->send(
                'PUT',
                $this->namespaceUri().'/bulk',
                $body,
                ['Content-Type' => 'application/json'],
            );

            $payload = json_decode((string) $response->getBody(), true);

            if (! is_array($payload) || ! ($payload['success'] ?? false)) {
                $success = false;
            } elseif (isset($payload['result']['unsuccessful_keys']) && $payload['result']['unsuccessful_keys'] !== []) {
                $success = false;
            }
        }

        return $success;
    }

    public function delete(string $key): bool
    {
        $response = $this->send('DELETE', $this->valueUri($key));

        return $this->isSuccessful($response);
    }

    public function deleteMany(array $keys): bool
    {
        if ($keys === []) {
            return true;
        }

        $success = true;

        foreach (array_chunk($keys, self::BULK_WRITE_LIMIT) as $chunk) {
            $body = json_encode($chunk, JSON_THROW_ON_ERROR);
            $response = $this->send(
                'POST',
                $this->namespaceUri().'/bulk/delete',
                $body,
                ['Content-Type' => 'application/json'],
            );

            $payload = json_decode((string) $response->getBody(), true);

            if (! is_array($payload) || ! ($payload['success'] ?? false)) {
                $success = false;
            } elseif (isset($payload['result']['unsuccessful_keys']) && $payload['result']['unsuccessful_keys'] !== []) {
                $success = false;
            }
        }

        return $success;
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

    /**
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $uri, mixed $body = null, array $headers = []): ResponseInterface
    {
        try {
            return $this->http->send(new Request($method, $uri, $headers, $body));
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

    private function metadataUri(string $key): string
    {
        return $this->namespaceUri().'/metadata/'.rawurlencode($key);
    }

    private function namespaceUri(): string
    {
        return sprintf(
            'accounts/%s/storage/kv/namespaces/%s',
            rawurlencode($this->accountId),
            rawurlencode($this->namespaceId),
        );
    }

    private function isSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
