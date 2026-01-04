<?php
/*
 * Created on   : Sun Dec 29 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BearerAuthenticationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Authentication;

use APIToolkit\API\Authentication\BearerAuthentication;
use PHPUnit\Framework\TestCase;

class BearerAuthenticationTest extends TestCase {
    public function testGetAuthHeaders(): void {
        $auth = new BearerAuthentication('my-secret-token');
        $headers = $auth->getAuthHeaders();

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer my-secret-token', $headers['Authorization']);
    }

    public function testGetType(): void {
        $auth = new BearerAuthentication('token');
        $this->assertEquals('Bearer', $auth->getType());
    }

    public function testIsValidWithToken(): void {
        $auth = new BearerAuthentication('valid-token');
        $this->assertTrue($auth->isValid());
    }

    public function testIsValidWithEmptyToken(): void {
        $auth = new BearerAuthentication('');
        $this->assertFalse($auth->isValid());
    }

    public function testGetAndSetToken(): void {
        $auth = new BearerAuthentication('initial-token');
        $this->assertEquals('initial-token', $auth->getToken());

        $auth->setToken('new-token');
        $this->assertEquals('new-token', $auth->getToken());
        $this->assertEquals('Bearer new-token', $auth->getAuthHeaders()['Authorization']);
    }

    public function testAdditionalHeadersInConstructor(): void {
        $auth = new BearerAuthentication('token', [
            'X-Datev-Client-ID' => 'client-123',
            'X-Custom-Header' => 'custom-value',
        ]);

        $headers = $auth->getAuthHeaders();

        $this->assertEquals('Bearer token', $headers['Authorization']);
        $this->assertEquals('client-123', $headers['X-Datev-Client-ID']);
        $this->assertEquals('custom-value', $headers['X-Custom-Header']);
    }

    public function testAddAndRemoveHeader(): void {
        $auth = new BearerAuthentication('token');

        $auth->addHeader('X-Client-ID', 'abc123');
        $this->assertArrayHasKey('X-Client-ID', $auth->getAuthHeaders());
        $this->assertEquals('abc123', $auth->getAuthHeaders()['X-Client-ID']);

        $auth->removeHeader('X-Client-ID');
        $this->assertArrayNotHasKey('X-Client-ID', $auth->getAuthHeaders());
    }

    public function testSetAdditionalHeaders(): void {
        $auth = new BearerAuthentication('token', ['Old-Header' => 'old']);

        $auth->setAdditionalHeaders([
            'New-Header-1' => 'value1',
            'New-Header-2' => 'value2',
        ]);

        $headers = $auth->getAdditionalHeaders();
        $this->assertArrayNotHasKey('Old-Header', $headers);
        $this->assertEquals('value1', $headers['New-Header-1']);
        $this->assertEquals('value2', $headers['New-Header-2']);
    }
}
