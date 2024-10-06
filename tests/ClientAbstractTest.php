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
use APIToolkit\Exceptions\UnauthorizedException;
use APIToolkit\Exceptions\TooManyRequestsException;
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
            ->with('GET', '/test-endpoint', ['http_errors' => false])
            ->willReturn($this->responseMock);

        $response = $this->client->get('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function testPostRequestSuccessful() {
        $this->responseMock->method('getStatusCode')->willReturn(201);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/test-endpoint', ['http_errors' => false])
            ->willReturn($this->responseMock);

        $response = $this->client->post('/test-endpoint');

        $this->assertEquals($this->responseMock, $response);
    }

    public function testThrowsBadRequestException() {
        $this->responseMock->method('getStatusCode')->willReturn(400);

        // Wir erwarten jetzt mehrere Aufrufe aufgrund der Retry-Logik
        $this->httpClientMock->expects($this->exactly(3)) // Maximal 3 Versuche
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(BadRequestException::class);

        $this->client->get('/bad-request-endpoint');
    }


    public function testThrowsUnauthorizedException() {
        $this->responseMock->method('getStatusCode')->willReturn(401);

        // Erwarte auch hier mehrere Aufrufe aufgrund der Retry-Logik
        $this->httpClientMock->expects($this->exactly(3)) // Maximal 3 Versuche
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(UnauthorizedException::class);

        $this->client->get('/unauthorized-endpoint');
    }


    public function testRetriesOnTooManyRequests() {
        $this->responseMock->method('getStatusCode')->willReturn(429);

        $this->httpClientMock->expects($this->exactly(3)) // Max retries is set to 3
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(TooManyRequestsException::class);

        $this->client->get('/too-many-requests-endpoint');
    }
}
