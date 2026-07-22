<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientExtensionsTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\API\Cache\Psr16ResponseCache;
use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{HttpFactory, Response};
use Psr\SimpleCache\CacheInterface;
use Tests\Contracts\Test;

class ClientExtensionsTest extends Test {
    private MockHandler $mockHandler;

    private function makeClient(array $queue): ClientAbstract {
        $this->mockHandler = new MockHandler($queue);
        $httpClient = new HttpClient(['handler' => HandlerStack::create($this->mockHandler)]);
        $client = new class('https://api.example.com', null, false, $httpClient) extends ClientAbstract {};
        $client->setRequestInterval(0.0);

        return $client;
    }

    private function arrayCache(): CacheInterface {
        return new class implements CacheInterface {
            /** @var array<string, mixed> */
            public array $data = [];

            public function get(string $key, mixed $default = null): mixed {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool {
                $this->data[$key] = $value;

                return true;
            }

            public function delete(string $key): bool {
                unset($this->data[$key]);

                return true;
            }

            public function clear(): bool {
                $this->data = [];

                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable {
                return [];
            }

            public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool {
                return true;
            }

            public function has(string $key): bool {
                return isset($this->data[$key]);
            }
        };
    }

    public function test_rate_limit_parsed_from_headers(): void {
        $client = $this->makeClient([
            new Response(200, ['X-RateLimit-Limit' => '100', 'X-RateLimit-Remaining' => '7', 'X-RateLimit-Reset' => (string) (time() + 30)], '{}'),
        ]);

        $this->assertNull($client->getLastRateLimit());
        $client->get('/x');

        $rl = $client->getLastRateLimit();
        $this->assertNotNull($rl);
        $this->assertSame(100, $rl->limit);
        $this->assertSame(7, $rl->remaining);
        $this->assertFalse($rl->isExhausted());
        $this->assertGreaterThan(0, $rl->secondsUntilReset());
    }

    public function test_request_middleware_can_mutate_options(): void {
        $client = $this->makeClient([new Response(200, [], '{}')]);
        $client->onRequest(function (string $method, string $uri, array $options): array {
            $options['headers']['X-Trace'] = 'abc-123';

            return $options;
        });

        $client->get('/x');

        $this->assertSame('abc-123', $this->mockHandler->getLastRequest()->getHeaderLine('X-Trace'));
    }

    public function test_request_middleware_can_short_circuit(): void {
        $client = $this->makeClient([]); // no network response queued
        $client->onRequest(fn (string $method, string $uri, array $options) => new Response(200, [], 'from-cache'));

        $response = $client->get('/x');
        $this->assertSame('from-cache', (string) $response->getBody());
    }

    public function test_pending_request_builder_lowers_to_options(): void {
        $client = $this->makeClient([new Response(201, [], '{}')]);

        $client->pending()
            ->withHeader('Accept', 'application/json')
            ->withJson(['amount' => 100])
            ->withIdempotencyKey('key-9')
            ->post('/charges');

        $sent = $this->mockHandler->getLastRequest();
        $this->assertSame('application/json', $sent->getHeaderLine('Accept'));
        $this->assertSame('key-9', $sent->getHeaderLine('Idempotency-Key'));
        $this->assertSame('{"amount":100}', (string) $sent->getBody());
    }

    public function test_response_cache_serves_fresh_entry_without_network(): void {
        $client = $this->makeClient([new Response(200, ['Content-Type' => 'application/json'], '{"v":1}')]);
        (new Psr16ResponseCache($this->arrayCache(), 300))->register($client);

        $first = (string) $client->get('/data')->getBody();
        // Only one response queued; a second GET must be served from cache.
        $second = (string) $client->get('/data')->getBody();

        $this->assertSame('{"v":1}', $first);
        $this->assertSame('{"v":1}', $second);
    }

    public function test_response_cache_revalidates_with_304(): void {
        $client = $this->makeClient([
            new Response(200, ['ETag' => '"abc"', 'Content-Type' => 'application/json'], '{"v":1}'),
            new Response(304, ['ETag' => '"abc"']),
        ]);
        // ttl 0 → never fresh, always revalidate via If-None-Match.
        (new Psr16ResponseCache($this->arrayCache(), 0))->register($client);

        $client->get('/data');
        $second = $client->get('/data');

        $this->assertSame('"abc"', $this->mockHandler->getLastRequest()->getHeaderLine('If-None-Match'));
        $this->assertSame('{"v":1}', (string) $second->getBody()); // 304 served from cache
    }

    public function test_psr18_transport_sends_json_request(): void {
        $client = $this->makeClient([new Response(200, [], '{"ok":true}')]);
        $factory = new HttpFactory;
        $client->setPsr18Transport(new HttpClient(['handler' => HandlerStack::create($this->mockHandler)]), $factory, $factory);

        $response = $client->post('/charges', ['json' => ['a' => 1]]);

        $this->assertSame('{"ok":true}', (string) $response->getBody());
        $sent = $this->mockHandler->getLastRequest();
        $this->assertSame('POST', $sent->getMethod());
        $this->assertStringContainsString('application/json', $sent->getHeaderLine('Content-Type'));
        $this->assertSame('{"a":1}', (string) $sent->getBody());
    }
}
