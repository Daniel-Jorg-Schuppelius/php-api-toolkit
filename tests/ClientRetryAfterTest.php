<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientRetryAfterTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Tests\Contracts\Test;

class ClientRetryAfterTest extends Test {
    private function makeClient(?HttpClient $httpClient = null): ClientAbstract {
        return new class('https://api.example.com', null, false, $httpClient) extends ClientAbstract {
            public function exposeResolveRetryDelay(int $attempt, ?ResponseInterface $response): int {
                return $this->resolveRetryDelay($attempt, $response);
            }

            public function exposeRetryAfterSeconds(?ResponseInterface $response): ?int {
                return $this->retryAfterSeconds($response);
            }
        };
    }

    public function test_retry_after_delta_seconds_is_honored() {
        $client = $this->makeClient();
        $response = new Response(429, ['Retry-After' => '5']);

        $this->assertSame(5, $client->exposeRetryAfterSeconds($response));
        $this->assertSame(5, $client->exposeResolveRetryDelay(1, $response));
    }

    public function test_retry_after_is_capped_at_max_retry_delay() {
        $client = $this->makeClient();
        $response = new Response(429, ['Retry-After' => '3600']);

        $this->assertSame(60, $client->exposeResolveRetryDelay(1, $response));

        $client->setMaxRetryDelay(10);
        $this->assertSame(10, $client->exposeResolveRetryDelay(1, $response));
    }

    public function test_retry_after_http_date_is_parsed() {
        $client = $this->makeClient();
        $response = new Response(429, ['Retry-After' => gmdate('D, d M Y H:i:s', time() + 30) . ' GMT']);

        $seconds = $client->exposeRetryAfterSeconds($response);

        $this->assertNotNull($seconds);
        $this->assertGreaterThanOrEqual(25, $seconds);
        $this->assertLessThanOrEqual(30, $seconds);
    }

    public function test_retry_after_http_date_in_the_past_yields_zero() {
        $client = $this->makeClient();
        $response = new Response(429, ['Retry-After' => gmdate('D, d M Y H:i:s', time() - 120) . ' GMT']);

        $this->assertSame(0, $client->exposeRetryAfterSeconds($response));
    }

    public function test_unparseable_retry_after_falls_back_to_backoff() {
        $client = $this->makeClient();
        $response = new Response(429, ['Retry-After' => 'not-a-date']);

        $this->assertNull($client->exposeRetryAfterSeconds($response));
        // exponential backoff: base 1s, attempt 2 => 2s
        $this->assertSame(2, $client->exposeResolveRetryDelay(2, $response));
    }

    public function test_missing_header_uses_backoff_capped_at_max_retry_delay() {
        $client = $this->makeClient();
        $response = new Response(429);

        $this->assertNull($client->exposeRetryAfterSeconds($response));
        $this->assertSame(1, $client->exposeResolveRetryDelay(1, $response));
        $this->assertSame(4, $client->exposeResolveRetryDelay(3, $response));

        $client->setBaseRetryDelay(50);
        // 50 * 2^2 = 200 => capped at default 60
        $this->assertSame(60, $client->exposeResolveRetryDelay(3, $response));
    }

    public function test_null_response_uses_backoff() {
        $client = $this->makeClient();

        $this->assertNull($client->exposeRetryAfterSeconds(null));
        $this->assertSame(1, $client->exposeResolveRetryDelay(1, null));
    }

    public function test_max_retry_delay_validation() {
        $client = $this->makeClient();

        $this->expectException(InvalidArgumentException::class);
        $client->setMaxRetryDelay(-1);
    }

    public function test_request_is_retried_after_too_many_requests_response() {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '0']),
            new Response(200, [], 'ok'),
        ]);
        $httpClient = new HttpClient(['handler' => HandlerStack::create($mock)]);

        $client = $this->makeClient($httpClient);
        $response = $client->get('/resource');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
        $this->assertSame(0, $mock->count(), 'Both queued responses should have been consumed');
    }
}
