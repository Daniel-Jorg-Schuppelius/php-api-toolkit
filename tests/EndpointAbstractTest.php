<?php

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\EndpointAbstract;
use APIToolkit\Contracts\Interfaces\API\ApiClientInterface;
use APIToolkit\Exceptions\ApiException;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Tests\Contracts\Test;

class EndpointAbstractTest extends Test {
    private $clientMock;
    private $loggerMock;
    private $responseMock;

    public function setUp(): void {
        parent::setUp();

        $this->clientMock = $this->createMock(ApiClientInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);
    }

    public function testGetContentsSuccessfulResponse() {
        $this->responseMock->method('getStatusCode')->willReturn(200);
        $this->responseMock->method('getBody')->willReturn($this->createMockBody('{"data":"test"}'));

        $this->clientMock->method('get')->willReturn($this->responseMock);

        $endpoint = $this->getMockBuilder(EndpointAbstract::class)
            ->setConstructorArgs([$this->clientMock, $this->loggerMock])
            ->onlyMethods(['get'])
            ->getMock();

        $reflection = new \ReflectionClass($endpoint);
        $property = $reflection->getProperty('endpoint');
        $property->setAccessible(true);
        $property->setValue($endpoint, 'test-endpoint'); // Setze hier den gewünschten Endpoint-Wert

        $method = $reflection->getMethod('getContents');
        $method->setAccessible(true);

        $response = $method->invoke($endpoint);

        $this->assertEquals('{"data":"test"}', $response);
    }

    public function testHandleResponseThrowsExceptionOnUnexpectedStatusCode() {
        $this->responseMock->method('getStatusCode')->willReturn(500);

        $endpoint = $this->getMockBuilder(EndpointAbstract::class)
            ->setConstructorArgs([$this->clientMock, $this->loggerMock])
            ->onlyMethods(['get'])
            ->getMock();

        $reflection = new \ReflectionClass($endpoint);
        $method = $reflection->getMethod('handleResponse');
        $method->setAccessible(true);

        $this->expectException(ApiException::class);
        $method->invoke($endpoint, $this->responseMock, 200);
    }

    private function createMockBody(string $content) {
        $bodyMock = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $bodyMock->method('getContents')->willReturn($content);
        return $bodyMock;
    }
}
