<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2DeviceCodeGrantTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\{OAuth2DeviceCodeGrant, OAuth2Token};
use APIToolkit\Exceptions\BadRequestException;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Tests\Contracts\Test;

class OAuth2DeviceCodeGrantTest extends Test {
    private function makeGrant(MockHandler $mock): OAuth2DeviceCodeGrant {
        // Subclass overrides wait() so polling does not actually sleep.
        return new class('device-client', '', 'https://provider.example.com/device/code', 'https://provider.example.com/token', null, new HttpClient(['handler' => HandlerStack::create($mock)])) extends OAuth2DeviceCodeGrant {
            protected function wait(int $seconds): void {}
        };
    }

    public function test_request_device_code_normalizes_interval(): void {
        $mock = new MockHandler([
            new Response(200, [], '{"device_code":"dev-123","user_code":"WXYZ-1234","verification_uri":"https://ex/activate","expires_in":900}'),
        ]);

        $result = $this->makeGrant($mock)->requestDeviceCode(['read']);

        $this->assertSame('dev-123', $result['device_code']);
        $this->assertSame('WXYZ-1234', $result['user_code']);
        $this->assertSame(5, $result['interval']); // default when omitted
        $this->assertSame(900, $result['expires_in']);
    }

    public function test_poll_returns_token_after_authorization_pending(): void {
        $mock = new MockHandler([
            new Response(400, [], '{"error":"authorization_pending"}'),
            new Response(400, [], '{"error":"slow_down"}'),
            new Response(200, [], '{"access_token":"tok-abc","token_type":"Bearer","expires_in":3600}'),
        ]);

        $token = $this->makeGrant($mock)->pollForToken('dev-123', 1);

        $this->assertInstanceOf(OAuth2Token::class, $token);
        $this->assertSame('tok-abc', $token->getAccessToken());
    }

    public function test_poll_throws_on_access_denied(): void {
        $mock = new MockHandler([
            new Response(400, [], '{"error":"authorization_pending"}'),
            new Response(400, [], '{"error":"access_denied"}'),
        ]);

        $this->expectException(BadRequestException::class);
        $this->makeGrant($mock)->pollForToken('dev-123', 1);
    }
}
