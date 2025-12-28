<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EndpointAbstractTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\EndpointAbstract;
use APIToolkit\Contracts\Interfaces\API\ApiClientInterface;
use APIToolkit\Exceptions\ApiException;
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
        $property->setValue($endpoint, 'test-endpoint'); // Setze hier den gewünschten Endpoint-Wert

        $method = $reflection->getMethod('getContents');

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

        $this->expectException(ApiException::class);
        $method->invoke($endpoint, $this->responseMock, 200);
    }

    private function createMockBody(string $content) {
        $bodyMock = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $bodyMock->method('getContents')->willReturn($content);
        return $bodyMock;
    }

    public function testPostContentsSuccessfulResponse() {
        $this->responseMock->method('getStatusCode')->willReturn(201);
        $this->responseMock->method('getBody')->willReturn($this->createMockBody('{"id":1}'));

        $this->clientMock->method('post')->willReturn($this->responseMock);

        $endpoint = $this->getMockBuilder(EndpointAbstract::class)
            ->setConstructorArgs([$this->clientMock, $this->loggerMock])
            ->onlyMethods(['get'])
            ->getMock();

        $reflection = new \ReflectionClass($endpoint);
        $property = $reflection->getProperty('endpoint');
        $property->setValue($endpoint, 'test-endpoint');

        $method = $reflection->getMethod('postContents');

        $response = $method->invoke($endpoint, ['name' => 'test']);

        $this->assertEquals('{"id":1}', $response);
    }

    public function testPutContentsSuccessfulResponse() {
        $this->responseMock->method('getStatusCode')->willReturn(200);
        $this->responseMock->method('getBody')->willReturn($this->createMockBody('{"updated":true}'));

        $this->clientMock->method('put')->willReturn($this->responseMock);

        $endpoint = $this->getMockBuilder(EndpointAbstract::class)
            ->setConstructorArgs([$this->clientMock, $this->loggerMock])
            ->onlyMethods(['get'])
            ->getMock();

        $reflection = new \ReflectionClass($endpoint);
        $property = $reflection->getProperty('endpoint');
        $property->setValue($endpoint, 'test-endpoint');

        $method = $reflection->getMethod('putContents');

        $response = $method->invoke($endpoint, ['name' => 'updated']);

        $this->assertEquals('{"updated":true}', $response);
    }

    public function testPatchContentsSuccessfulResponse() {
        $this->responseMock->method('getStatusCode')->willReturn(200);
        $this->responseMock->method('getBody')->willReturn($this->createMockBody('{"patched":true}'));

        $this->clientMock->method('patch')->willReturn($this->responseMock);

        $endpoint = $this->getMockBuilder(EndpointAbstract::class)
            ->setConstructorArgs([$this->clientMock, $this->loggerMock])
            ->onlyMethods(['get'])
            ->getMock();

        $reflection = new \ReflectionClass($endpoint);
        $property = $reflection->getProperty('endpoint');
        $property->setValue($endpoint, 'test-endpoint');

        $method = $reflection->getMethod('patchContents');

        $response = $method->invoke($endpoint, ['field' => 'value']);

        $this->assertEquals('{"patched":true}', $response);
    }

    public function testDeleteContentsSuccessfulResponse() {
        $this->responseMock->method('getStatusCode')->willReturn(204);

        $this->clientMock->method('delete')->willReturn($this->responseMock);

        $endpoint = $this->getMockBuilder(EndpointAbstract::class)
            ->setConstructorArgs([$this->clientMock, $this->loggerMock])
            ->onlyMethods(['get'])
            ->getMock();

        $reflection = new \ReflectionClass($endpoint);
        $property = $reflection->getProperty('endpoint');
        $property->setValue($endpoint, 'test-endpoint');

        $method = $reflection->getMethod('deleteContents');

        $response = $method->invoke($endpoint);

        $this->assertEquals('success', $response);
    }

    public function testHandleResponseReturnsSuccessOn204() {
        $this->responseMock->method('getStatusCode')->willReturn(204);

        $endpoint = $this->getMockBuilder(EndpointAbstract::class)
            ->setConstructorArgs([$this->clientMock, $this->loggerMock])
            ->onlyMethods(['get'])
            ->getMock();

        $reflection = new \ReflectionClass($endpoint);
        $method = $reflection->getMethod('handleResponse');

        $response = $method->invoke($endpoint, $this->responseMock, 204);

        $this->assertEquals('success', $response);
    }
}
