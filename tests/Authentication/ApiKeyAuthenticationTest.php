<?php
/*
 * Created on   : Sun Dec 29 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiKeyAuthenticationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Authentication;

use APIToolkit\Contracts\Abstracts\API\Authentication\ApiKeyAuthentication;
use PHPUnit\Framework\TestCase;

class ApiKeyAuthenticationTest extends TestCase {
    public function testGetAuthHeadersWithDefaultHeaderName(): void {
        $auth = new ApiKeyAuthentication('my-api-key');
        $headers = $auth->getAuthHeaders();

        $this->assertArrayHasKey('X-API-Key', $headers);
        $this->assertEquals('my-api-key', $headers['X-API-Key']);
    }

    public function testGetAuthHeadersWithCustomHeaderName(): void {
        $auth = new ApiKeyAuthentication('my-api-key', 'X-Custom-Auth');
        $headers = $auth->getAuthHeaders();

        $this->assertArrayHasKey('X-Custom-Auth', $headers);
        $this->assertEquals('my-api-key', $headers['X-Custom-Auth']);
    }

    public function testGetType(): void {
        $auth = new ApiKeyAuthentication('key');
        $this->assertEquals('ApiKey', $auth->getType());
    }

    public function testIsValidWithApiKey(): void {
        $auth = new ApiKeyAuthentication('valid-key');
        $this->assertTrue($auth->isValid());
    }

    public function testIsValidWithEmptyApiKey(): void {
        $auth = new ApiKeyAuthentication('');
        $this->assertFalse($auth->isValid());
    }

    public function testGetAndSetApiKey(): void {
        $auth = new ApiKeyAuthentication('initial-key');
        $this->assertEquals('initial-key', $auth->getApiKey());

        $auth->setApiKey('new-key');
        $this->assertEquals('new-key', $auth->getApiKey());
    }

    public function testGetAndSetHeaderName(): void {
        $auth = new ApiKeyAuthentication('key', 'Initial-Header');
        $this->assertEquals('Initial-Header', $auth->getHeaderName());

        $auth->setHeaderName('New-Header');
        $this->assertEquals('New-Header', $auth->getHeaderName());
        $this->assertArrayHasKey('New-Header', $auth->getAuthHeaders());
    }
}
