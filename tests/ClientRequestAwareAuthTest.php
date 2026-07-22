<?php
/*
 * Created on   : Thu Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientRequestAwareAuthTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Contracts\Interfaces\API\RequestAwareAuthenticationInterface;
use GuzzleHttp\Client as HttpClient;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Tests\Contracts\Test;

class ClientRequestAwareAuthTest extends Test {
    private $httpClientMock;
    private $responseMock;
    private ClientAbstract $client;

    protected function setUp(): void {
        parent::setUp();

        $this->httpClientMock = $this->createMock(HttpClient::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);

        $this->client = new class('https://api.example.com', null, false, $this->httpClientMock) extends ClientAbstract {};
    }

    private function requestAwareAuth(): RequestAwareAuthenticationInterface {
        return new class implements RequestAwareAuthenticationInterface {
            /** @var array{method: string, uri: string, body: ?string}|null */
            public ?array $lastRequest = null;

            public function getAuthHeadersFor(string $method, string $uri, ?string $body = null): array {
                $this->lastRequest = ['method' => $method, 'uri' => $uri, 'body' => $body];

                return ['Authorization' => "Signed {$method}:{$uri}:" . ($body ?? '-')];
            }

            public function getAuthHeaders(): array {
                return ['Authorization' => 'Signed static'];
            }

            public function getType(): string {
                return 'RequestAwareSignature';
            }

            public function isValid(): bool {
                return true;
            }
        };
    }

    public function test_request_aware_authentication_signs_method_and_uri() {
        $auth = $this->requestAwareAuth();
        $this->client->setAuthentication($auth);

        $this->responseMock->method('getStatusCode')->willReturn(200);
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('GET', '/sessions', $this->callback(function (array $options): bool {
                return ($options['headers']['Authorization'] ?? null) === 'Signed GET:/sessions:-';
            }))
            ->willReturn($this->responseMock);

        $this->client->get('/sessions');

        $this->assertSame('GET', $auth->lastRequest['method']);
        $this->assertSame('/sessions', $auth->lastRequest['uri']);
        $this->assertNull($auth->lastRequest['body']);
    }

    public function test_request_aware_authentication_receives_raw_body() {
        $auth = $this->requestAwareAuth();
        $this->client->setAuthentication($auth);

        $this->responseMock->method('getStatusCode')->willReturn(201);
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->client->post('/items', ['body' => '{"raw":true}']);

        $this->assertSame('{"raw":true}', $auth->lastRequest['body']);
    }

    public function test_request_aware_authentication_receives_json_body_as_guzzle_encodes_it() {
        $auth = $this->requestAwareAuth();
        $this->client->setAuthentication($auth);

        $this->responseMock->method('getStatusCode')->willReturn(200);
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->client->post('/items', ['json' => ['a' => 1, 'b' => 'x']]);

        $this->assertSame(json_encode(['a' => 1, 'b' => 'x']), $auth->lastRequest['body']);
    }

    public function test_per_request_timeout_overrides_client_default() {
        $this->responseMock->method('getStatusCode')->willReturn(200);
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('GET', '/slow-report', $this->callback(function (array $options): bool {
                return $options['timeout'] === 60.0 && $options['connect_timeout'] === 10.0;
            }))
            ->willReturn($this->responseMock);

        $this->client->get('/slow-report', ['timeout' => 60.0]);
    }

    public function test_zero_request_interval_disables_throttling() {
        $this->client->setRequestInterval(0.0);
        $this->assertSame(0.0, $this->client->getRequestInterval());
    }

    public function test_request_interval_below_minimum_is_rejected() {
        $this->expectException(InvalidArgumentException::class);
        $this->client->setRequestInterval(0.1);
    }

    public function test_zero_retry_delays_are_allowed_for_tests() {
        $this->client->setBaseRetryDelay(0);
        $this->client->setMaxRetryDelay(0);

        $this->assertSame(0, $this->client->getBaseRetryDelay());
        $this->assertSame(0, $this->client->getMaxRetryDelay());
    }

    public function test_negative_retry_delays_are_rejected() {
        $this->expectException(InvalidArgumentException::class);
        $this->client->setBaseRetryDelay(-1);
    }

    public function test_negative_max_retry_delay_is_rejected() {
        $this->expectException(InvalidArgumentException::class);
        $this->client->setMaxRetryDelay(-1);
    }

    public function test_zero_max_retry_delay_caps_retry_after_header() {
        $this->client->setBaseRetryDelay(0);
        $this->client->setMaxRetryDelay(0);

        $rateLimited = $this->createMock(ResponseInterface::class);
        $rateLimited->method('getStatusCode')->willReturn(429);
        // Tolerate the client's rate-limit header probes; only Retry-After is set.
        $rateLimited->method('hasHeader')->willReturnCallback(fn (string $name): bool => strtolower($name) === 'retry-after');
        $rateLimited->method('getHeaderLine')->willReturnCallback(fn (string $name): string => strtolower($name) === 'retry-after' ? '120' : '');

        $ok = $this->createMock(ResponseInterface::class);
        $ok->method('getStatusCode')->willReturn(200);

        $this->httpClientMock->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($rateLimited, $ok);

        $start = microtime(true);
        $response = $this->client->get('/throttled');
        $elapsed = microtime(true) - $start;

        $this->assertSame(200, $response->getStatusCode());
        $this->assertLessThan(1.0, $elapsed, 'Retry with maxRetryDelay=0 must not sleep despite Retry-After: 120');
    }
}
