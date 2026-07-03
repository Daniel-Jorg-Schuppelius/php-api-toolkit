<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientAbstractTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Exceptions\{BadRequestException, ForbiddenException, InternalServerErrorException, NotFoundException, RequestTimeoutException, ServiceUnavailableException, TooManyRequestsException, UnauthorizedException, UnprocessableEntityException};
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Tests\Contracts\Test;

class ClientAbstractTest extends Test {
    private $httpClientMock;
    private $loggerMock;
    private $responseMock;
    private $client;

    protected function setUp(): void {
        parent::setUp();

        $this->httpClientMock = $this->createMock(HttpClient::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->client = $this->getMockBuilder(ClientAbstract::class)
            ->setConstructorArgs(['https://api.example.com', $this->loggerMock, false, $this->httpClientMock])
            ->onlyMethods(['request'])
            ->getMock();
    }

    public function test_get_request_successful() {
        $this->responseMock->method('getStatusCode')->willReturn(200);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('GET', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->get('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function test_post_request_successful() {
        $this->responseMock->method('getStatusCode')->willReturn(201);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->post('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function test_throws_bad_request_exception() {
        $this->responseMock->method('getStatusCode')->willReturn(400);

        // Wir erwarten jetzt mehrere Aufrufe aufgrund der Retry-Logik
        $this->httpClientMock->expects($this->exactly(1)) // Maximal 1 Versuche
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(BadRequestException::class);

        $this->client->get('/bad-request-endpoint');
    }

    public function test_throws_unauthorized_exception() {
        $this->responseMock->method('getStatusCode')->willReturn(401);

        // Erwarte auch hier mehrere Aufrufe aufgrund der Retry-Logik
        $this->httpClientMock->expects($this->exactly(1)) // Maximal 1 Versuche
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(UnauthorizedException::class);

        $this->client->get('/unauthorized-endpoint');
    }

    public function test_retries_on_too_many_requests() {
        $this->responseMock->method('getStatusCode')->willReturn(429);

        // Default maxRetries is now 3, so we expect 3 attempts
        $this->httpClientMock->expects($this->exactly(3))
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(TooManyRequestsException::class);

        $this->client->get('/too-many-requests-endpoint');
    }

    public function test_retries_with_custom_max_retries() {
        $this->responseMock->method('getStatusCode')->willReturn(429);

        // Set maxRetries to 2
        $this->client->setMaxRetries(2);

        $this->httpClientMock->expects($this->exactly(2))
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(TooManyRequestsException::class);

        $this->client->get('/too-many-requests-endpoint');
    }

    public function test_set_request_interval_throws_exception_for_low_value() {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->setRequestInterval(0.1);
    }

    public function test_set_max_retries_throws_exception_for_low_value() {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->setMaxRetries(0);
    }

    public function test_exponential_backoff_getter_and_setter() {
        $this->assertTrue($this->client->isExponentialBackoffEnabled());
        $this->client->setExponentialBackoff(false);
        $this->assertFalse($this->client->isExponentialBackoffEnabled());
    }

    public function test_patch_request_successful() {
        $this->responseMock->method('getStatusCode')->willReturn(200);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('PATCH', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->patch('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function test_put_request_successful() {
        $this->responseMock->method('getStatusCode')->willReturn(200);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('PUT', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->put('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function test_delete_request_successful() {
        $this->responseMock->method('getStatusCode')->willReturn(204);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('DELETE', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->delete('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function test_throws_not_found_exception() {
        $this->responseMock->method('getStatusCode')->willReturn(404);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(NotFoundException::class);

        $this->client->get('/not-found-endpoint');
    }

    public function test_throws_forbidden_exception() {
        $this->responseMock->method('getStatusCode')->willReturn(403);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(ForbiddenException::class);

        $this->client->get('/forbidden-endpoint');
    }

    public function test_throws_request_timeout_exception() {
        $this->responseMock->method('getStatusCode')->willReturn(408);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(RequestTimeoutException::class);

        $this->client->get('/timeout-endpoint');
    }

    public function test_throws_unprocessable_entity_exception() {
        $this->responseMock->method('getStatusCode')->willReturn(422);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(UnprocessableEntityException::class);

        $this->client->get('/unprocessable-endpoint');
    }

    public function test_throws_internal_server_error_exception() {
        $this->responseMock->method('getStatusCode')->willReturn(500);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(InternalServerErrorException::class);

        $this->client->get('/server-error-endpoint');
    }

    public function test_retries_on_service_unavailable() {
        $this->responseMock->method('getStatusCode')->willReturn(503);

        // ServiceUnavailable triggers retry, so expect 3 attempts
        $this->httpClientMock->expects($this->exactly(3))
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(ServiceUnavailableException::class);

        $this->client->get('/unavailable-endpoint');
    }

    public function test_base_retry_delay_getter_and_setter() {
        $this->assertEquals(1, $this->client->getBaseRetryDelay());
        $this->client->setBaseRetryDelay(5);
        $this->assertEquals(5, $this->client->getBaseRetryDelay());
    }

    public function test_set_base_retry_delay_throws_exception_for_low_value() {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->setBaseRetryDelay(-1);
    }

    public function test_get_request_interval() {
        $this->assertEquals(0.25, $this->client->getRequestInterval());
        $this->client->setRequestInterval(0.5);
        $this->assertEquals(0.5, $this->client->getRequestInterval());
    }

    public function test_get_max_retries() {
        $this->assertEquals(3, $this->client->getMaxRetries());
        $this->client->setMaxRetries(5);
        $this->assertEquals(5, $this->client->getMaxRetries());
    }
}
