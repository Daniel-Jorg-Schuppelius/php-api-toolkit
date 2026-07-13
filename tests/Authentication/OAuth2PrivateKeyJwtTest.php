<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2PrivateKeyJwtTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsGrant;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use RuntimeException;
use Tests\Contracts\Test;

class OAuth2PrivateKeyJwtTest extends Test {
    private static ?string $privateKeyPem = null;
    private static ?string $publicKeyPem = null;
    private static ?string $certificatePem = null;

    public static function setUpBeforeClass(): void {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false) {
            self::fail('Could not generate RSA test key');
        }

        openssl_pkey_export($key, $privateKeyPem);
        self::$privateKeyPem = $privateKeyPem;

        $details = openssl_pkey_get_details($key);
        self::$publicKeyPem = $details !== false ? $details['key'] : null;

        $csr = openssl_csr_new(['commonName' => 'api-toolkit-test'], $key);
        $cert = $csr !== false ? openssl_csr_sign($csr, null, $key, 1) : false;
        if ($cert !== false) {
            openssl_x509_export($cert, $certificatePem);
            self::$certificatePem = $certificatePem;
        }
    }

    private function makeGrant(?MockHandler $mock = null): OAuth2ClientCredentialsGrant {
        $httpClient = $mock !== null
            ? new HttpClient(['handler' => HandlerStack::create($mock)])
            : null;

        return new OAuth2ClientCredentialsGrant(
            'client-id',
            '', // no client secret — assertion-based client
            'https://provider.example.com/oauth/token',
            null,
            $httpClient
        );
    }

    private static function tokenResponse(): Response {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'access_token' => 'at',
            'expires_in' => 3600,
        ]));
    }

    private static function base64UrlDecode(string $value): string {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }

    public function test_private_key_jwt_sends_signed_client_assertion() {
        $mock = new MockHandler([self::tokenResponse()]);
        $grant = $this->makeGrant($mock);
        $grant->setPrivateKeyJwt((string) self::$privateKeyPem);

        $this->assertSame(OAuth2ClientCredentialsGrant::AUTH_METHOD_PRIVATE_KEY_JWT, $grant->getTokenAuthMethod());

        $grant->fetchToken(['https://graph.microsoft.com/.default']);

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('client_credentials', $body['grant_type']);
        $this->assertSame('client-id', $body['client_id']);
        $this->assertSame('urn:ietf:params:oauth:client-assertion-type:jwt-bearer', $body['client_assertion_type']);
        $this->assertArrayNotHasKey('client_secret', $body);

        [$header64, $claims64, $signature64] = explode('.', $body['client_assertion']);

        $header = json_decode(self::base64UrlDecode($header64), true);
        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
        $this->assertArrayNotHasKey('x5t', $header);

        $claims = json_decode(self::base64UrlDecode($claims64), true);
        $this->assertSame('client-id', $claims['iss']);
        $this->assertSame('client-id', $claims['sub']);
        $this->assertSame('https://provider.example.com/oauth/token', $claims['aud']);
        $this->assertNotEmpty($claims['jti']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertLessThanOrEqual(time(), $claims['nbf']);

        $this->assertSame(1, openssl_verify(
            "{$header64}.{$claims64}",
            self::base64UrlDecode($signature64),
            (string) self::$publicKeyPem,
            OPENSSL_ALGO_SHA256
        ), 'Client assertion signature must verify against the public key');
    }

    public function test_certificate_adds_x5t_thumbprints_to_header() {
        if (self::$certificatePem === null) {
            $this->markTestSkipped('Could not create a self-signed test certificate');
        }

        $mock = new MockHandler([self::tokenResponse()]);
        $grant = $this->makeGrant($mock);
        $grant->setPrivateKeyJwt((string) self::$privateKeyPem, self::$certificatePem);

        $grant->fetchToken();

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str((string) $request->getBody(), $body);
        [$header64] = explode('.', $body['client_assertion']);
        $header = json_decode(self::base64UrlDecode($header64), true);

        $expectedSha1 = openssl_x509_fingerprint(self::$certificatePem, 'sha1', true);
        $expectedSha256 = openssl_x509_fingerprint(self::$certificatePem, 'sha256', true);
        $this->assertSame(rtrim(strtr(base64_encode((string) $expectedSha1), '+/', '-_'), '='), $header['x5t']);
        $this->assertSame(rtrim(strtr(base64_encode((string) $expectedSha256), '+/', '-_'), '='), $header['x5t#S256']);
    }

    public function test_each_assertion_gets_a_unique_jti() {
        $mock = new MockHandler([self::tokenResponse(), self::tokenResponse()]);
        $grant = $this->makeGrant($mock);
        $grant->setPrivateKeyJwt((string) self::$privateKeyPem);

        $jtis = [];
        foreach (range(1, 2) as $i) {
            $grant->fetchToken();
            $request = $mock->getLastRequest();
            $this->assertNotNull($request);
            parse_str((string) $request->getBody(), $body);
            [, $claims64] = explode('.', $body['client_assertion']);
            $claims = json_decode(self::base64UrlDecode($claims64), true);
            $jtis[] = $claims['jti'];
        }

        $this->assertNotSame($jtis[0], $jtis[1]);
    }

    public function test_empty_private_key_is_rejected() {
        $grant = $this->makeGrant();

        $this->expectException(InvalidArgumentException::class);
        $grant->setPrivateKeyJwt('');
    }

    public function test_invalid_assertion_lifetime_is_rejected() {
        $grant = $this->makeGrant();

        $this->expectException(InvalidArgumentException::class);
        $grant->setPrivateKeyJwt((string) self::$privateKeyPem, null, null, 0);
    }

    public function test_private_key_jwt_method_without_key_is_rejected_at_fetch() {
        $grant = $this->makeGrant(new MockHandler([self::tokenResponse()]));
        $grant->setTokenAuthMethod(OAuth2ClientCredentialsGrant::AUTH_METHOD_PRIVATE_KEY_JWT);

        $this->expectException(RuntimeException::class);
        $grant->fetchToken();
    }

    public function test_unloadable_private_key_throws() {
        $grant = $this->makeGrant(new MockHandler([self::tokenResponse()]));
        $grant->setPrivateKeyJwt('not-a-pem-key');

        $this->expectException(RuntimeException::class);
        $grant->fetchToken();
    }
}
