<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HmacAuthenticationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\HmacAuthentication;
use APIToolkit\Contracts\Interfaces\API\RequestAwareAuthenticationInterface;
use InvalidArgumentException;
use Tests\Contracts\Test;

class HmacAuthenticationTest extends Test {
    public function test_is_a_request_aware_authentication(): void {
        $this->assertInstanceOf(RequestAwareAuthenticationInterface::class, new HmacAuthentication('key', 'secret'));
    }

    public function test_signs_request_with_key_id_and_timestamp(): void {
        $auth = new HmacAuthentication('key-1', 'topsecret');

        $headers = $auth->getAuthHeadersFor('POST', '/charges', '{"amount":100}');

        $this->assertSame('key-1', $headers['X-Key-Id']);
        $this->assertArrayHasKey('X-Timestamp', $headers);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $headers['X-Signature']);
    }

    public function test_signature_binds_method_uri_and_body(): void {
        $auth = new HmacAuthentication('key-1', 'topsecret');

        // Reconstruct the expected signature deterministically using the
        // timestamp the auth emitted, then vary each component.
        $base = $auth->getAuthHeadersFor('POST', '/a', 'body-1');
        $ts = $base['X-Timestamp'];

        $expected = hash_hmac('sha256', "POST\n/a\n{$ts}\n" . hash('sha256', 'body-1'), 'topsecret');
        $this->assertSame($expected, $base['X-Signature']);

        // A different body under the same timestamp yields a different sig.
        $other = hash_hmac('sha256', "POST\n/a\n{$ts}\n" . hash('sha256', 'body-2'), 'topsecret');
        $this->assertNotSame($base['X-Signature'], $other);
    }

    public function test_validity_and_debug_redaction(): void {
        $this->assertTrue((new HmacAuthentication('k', 's'))->isValid());
        $this->assertFalse((new HmacAuthentication('', 's'))->isValid());
        $this->assertFalse((new HmacAuthentication('k', ''))->isValid());

        $dump = (new HmacAuthentication('k', 'super'))->__debugInfo();
        $this->assertSame('[redacted]', $dump['secret']);
        $this->assertSame('HMAC', (new HmacAuthentication('k', 's'))->getType());
    }

    public function test_rejects_unknown_algorithm(): void {
        $this->expectException(InvalidArgumentException::class);
        new HmacAuthentication('k', 's', 'nope-algo');
    }
}
