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
use APIToolkit\Exceptions\BadRequestException;
use APIToolkit\Exceptions\ForbiddenException;
use APIToolkit\Exceptions\InternalServerErrorException;
use APIToolkit\Exceptions\NotFoundException;
use APIToolkit\Exceptions\RequestTimeoutException;
use APIToolkit\Exceptions\ServiceUnavailableException;
use APIToolkit\Exceptions\UnauthorizedException;
use APIToolkit\Exceptions\TooManyRequestsException;
use APIToolkit\Exceptions\UnprocessableEntityException;
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
            ->setConstructorArgs([$this->httpClientMock, $this->loggerMock])
            ->onlyMethods(['request'])
            ->getMock();
    }

    public function testGetRequestSuccessful() {
        $this->responseMock->method('getStatusCode')->willReturn(200);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('GET', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->get('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function testPostRequestSuccessful() {
        $this->responseMock->method('getStatusCode')->willReturn(201);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->post('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function testThrowsBadRequestException() {
        $this->responseMock->method('getStatusCode')->willReturn(400);

        // Wir erwarten jetzt mehrere Aufrufe aufgrund der Retry-Logik
        $this->httpClientMock->expects($this->exactly(1)) // Maximal 1 Versuche
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(BadRequestException::class);

        $this->client->get('/bad-request-endpoint');
    }


    public function testThrowsUnauthorizedException() {
        $this->responseMock->method('getStatusCode')->willReturn(401);

        // Erwarte auch hier mehrere Aufrufe aufgrund der Retry-Logik
        $this->httpClientMock->expects($this->exactly(1)) // Maximal 1 Versuche
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(UnauthorizedException::class);

        $this->client->get('/unauthorized-endpoint');
    }


    public function testRetriesOnTooManyRequests() {
        $this->responseMock->method('getStatusCode')->willReturn(429);

        // Default maxRetries is now 3, so we expect 3 attempts
        $this->httpClientMock->expects($this->exactly(3))
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(TooManyRequestsException::class);

        $this->client->get('/too-many-requests-endpoint');
    }

    public function testRetriesWithCustomMaxRetries() {
        $this->responseMock->method('getStatusCode')->willReturn(429);

        // Set maxRetries to 2
        $this->client->setMaxRetries(2);

        $this->httpClientMock->expects($this->exactly(2))
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(TooManyRequestsException::class);

        $this->client->get('/too-many-requests-endpoint');
    }

    public function testSetRequestIntervalThrowsExceptionForLowValue() {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->setRequestInterval(0.1);
    }

    public function testSetMaxRetriesThrowsExceptionForLowValue() {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->setMaxRetries(0);
    }

    public function testExponentialBackoffGetterAndSetter() {
        $this->assertTrue($this->client->isExponentialBackoffEnabled());
        $this->client->setExponentialBackoff(false);
        $this->assertFalse($this->client->isExponentialBackoffEnabled());
    }

    public function testPatchRequestSuccessful() {
        $this->responseMock->method('getStatusCode')->willReturn(200);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('PATCH', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->patch('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function testPutRequestSuccessful() {
        $this->responseMock->method('getStatusCode')->willReturn(200);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('PUT', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->put('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function testDeleteRequestSuccessful() {
        $this->responseMock->method('getStatusCode')->willReturn(204);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('DELETE', '/test-endpoint', ['http_errors' => false, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0])
            ->willReturn($this->responseMock);

        $response = $this->client->delete('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function testThrowsNotFoundException() {
        $this->responseMock->method('getStatusCode')->willReturn(404);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(NotFoundException::class);

        $this->client->get('/not-found-endpoint');
    }

    public function testThrowsForbiddenException() {
        $this->responseMock->method('getStatusCode')->willReturn(403);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(ForbiddenException::class);

        $this->client->get('/forbidden-endpoint');
    }

    public function testThrowsRequestTimeoutException() {
        $this->responseMock->method('getStatusCode')->willReturn(408);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(RequestTimeoutException::class);

        $this->client->get('/timeout-endpoint');
    }

    public function testThrowsUnprocessableEntityException() {
        $this->responseMock->method('getStatusCode')->willReturn(422);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(UnprocessableEntityException::class);

        $this->client->get('/unprocessable-endpoint');
    }

    public function testThrowsInternalServerErrorException() {
        $this->responseMock->method('getStatusCode')->willReturn(500);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(InternalServerErrorException::class);

        $this->client->get('/server-error-endpoint');
    }

    public function testRetriesOnServiceUnavailable() {
        $this->responseMock->method('getStatusCode')->willReturn(503);

        // ServiceUnavailable triggers retry, so expect 3 attempts
        $this->httpClientMock->expects($this->exactly(3))
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(ServiceUnavailableException::class);

        $this->client->get('/unavailable-endpoint');
    }

    public function testBaseRetryDelayGetterAndSetter() {
        $this->assertEquals(1, $this->client->getBaseRetryDelay());
        $this->client->setBaseRetryDelay(5);
        $this->assertEquals(5, $this->client->getBaseRetryDelay());
    }

    public function testSetBaseRetryDelayThrowsExceptionForLowValue() {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->setBaseRetryDelay(0);
    }

    public function testGetRequestInterval() {
        $this->assertEquals(0.25, $this->client->getRequestInterval());
        $this->client->setRequestInterval(0.5);
        $this->assertEquals(0.5, $this->client->getRequestInterval());
    }

    public function testGetMaxRetries() {
        $this->assertEquals(3, $this->client->getMaxRetries());
        $this->client->setMaxRetries(5);
        $this->assertEquals(5, $this->client->getMaxRetries());
    }
}
