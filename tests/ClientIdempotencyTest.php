<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientIdempotencyTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Request, Response};
use Tests\Contracts\Test;

class ClientIdempotencyTest extends Test {
    private MockHandler $mockHandler;

    private function makeClient(array $queue): ClientAbstract {
        $this->mockHandler = new MockHandler($queue);
        $httpClient = new HttpClient(['handler' => HandlerStack::create($this->mockHandler)]);

        $client = new class('https://api.example.com', null, false, $httpClient) extends ClientAbstract {};
        $client->setRequestInterval(0.0);
        $client->setBaseRetryDelay(0);

        return $client;
    }

    public function test_auto_key_added_to_post_when_enabled(): void {
        $client = $this->makeClient([new Response(200, [], '{}')]);
        $client->setAutoIdempotencyKey(true);

        $client->post('/charges', ['json' => ['amount' => 100]]);

        $sent = $this->mockHandler->getLastRequest();
        $this->assertNotNull($sent);
        $this->assertTrue($sent->hasHeader('Idempotency-Key'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $sent->getHeaderLine('Idempotency-Key'));
    }

    public function test_no_key_on_get_or_when_disabled(): void {
        $client = $this->makeClient([new Response(200, [], '{}'), new Response(200, [], '{}')]);

        // GET with auto enabled → still no key (GET is not mutating)
        $client->setAutoIdempotencyKey(true);
        $client->get('/charges');
        $this->assertFalse($this->mockHandler->getLastRequest()->hasHeader('Idempotency-Key'));

        // POST with auto disabled → no key
        $client->setAutoIdempotencyKey(false);
        $client->post('/charges', ['json' => []]);
        $this->assertFalse($this->mockHandler->getLastRequest()->hasHeader('Idempotency-Key'));
    }

    public function test_explicit_key_wins_and_custom_header_name(): void {
        $client = $this->makeClient([new Response(200, [], '{}')]);
        $client->setIdempotencyHeader('X-Idempotency-Token');

        $client->post('/charges', ['idempotency_key' => 'my-key-123', 'json' => []]);

        $sent = $this->mockHandler->getLastRequest();
        $this->assertSame('my-key-123', $sent->getHeaderLine('X-Idempotency-Token'));
    }

    public function test_same_key_is_reused_across_retries(): void {
        // First attempt fails with a connection error, second succeeds.
        $client = $this->makeClient([
            new ConnectException('boom', new Request('POST', '/charges')),
            new Response(200, [], '{}'),
        ]);
        $client->setAutoIdempotencyKey(true);

        $keys = [];
        $this->mockHandler->reset();
        $this->mockHandler->append(
            function ($request) use (&$keys) {
                $keys[] = $request->getHeaderLine('Idempotency-Key');
                throw new ConnectException('boom', $request);
            },
            function ($request) use (&$keys) {
                $keys[] = $request->getHeaderLine('Idempotency-Key');

                return new Response(200, [], '{}');
            }
        );

        $client->post('/charges', ['json' => []]);

        $this->assertCount(2, $keys);
        $this->assertNotSame('', $keys[0]);
        $this->assertSame($keys[0], $keys[1], 'retry must reuse the same idempotency key');
    }
}
