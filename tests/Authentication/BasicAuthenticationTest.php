<?php
/*
 * Created on   : Sun Dec 29 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BasicAuthenticationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Authentication;

use APIToolkit\API\Authentication\BasicAuthentication;
use PHPUnit\Framework\TestCase;

class BasicAuthenticationTest extends TestCase {
    public function testGetAuthHeaders(): void {
        $auth = new BasicAuthentication('user', 'password');
        $headers = $auth->getAuthHeaders();

        $this->assertArrayHasKey('Authorization', $headers);
        $expectedCredentials = base64_encode('user:password');
        $this->assertEquals('Basic ' . $expectedCredentials, $headers['Authorization']);
    }

    public function testGetType(): void {
        $auth = new BasicAuthentication('user', 'pass');
        $this->assertEquals('Basic', $auth->getType());
    }

    public function testIsValidWithUsername(): void {
        $auth = new BasicAuthentication('user', '');
        $this->assertTrue($auth->isValid());
    }

    public function testIsValidWithEmptyUsername(): void {
        $auth = new BasicAuthentication('', 'password');
        $this->assertFalse($auth->isValid());
    }

    public function testGetAndSetCredentials(): void {
        $auth = new BasicAuthentication('initial-user', 'initial-pass');
        $this->assertEquals('initial-user', $auth->getUsername());

        $auth->setUsername('new-user');
        $auth->setPassword('new-pass');

        $expectedCredentials = base64_encode('new-user:new-pass');
        $this->assertEquals('Basic ' . $expectedCredentials, $auth->getAuthHeaders()['Authorization']);
    }
}
