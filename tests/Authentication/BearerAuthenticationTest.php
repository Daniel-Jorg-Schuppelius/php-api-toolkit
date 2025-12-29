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

use APIToolkit\Contracts\Abstracts\API\Authentication\BearerAuthentication;
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
}
