<?php

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Exceptions\BadRequestException;
use APIToolkit\Exceptions\UnauthorizedException;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;
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

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(BadRequestException::class);

        $this->client->get('/bad-request-endpoint');
    }

    public function testThrowsUnauthorizedException() {
        $this->responseMock->method('getStatusCode')->willReturn(401);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->responseMock);

        $this->expectException(UnauthorizedException::class);

        $this->client->get('/unauthorized-endpoint');
    }
}