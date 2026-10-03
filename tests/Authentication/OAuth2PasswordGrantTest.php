<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2PasswordGrantTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\OAuth2PasswordGrant;
use APIToolkit\Exceptions\UnauthorizedException;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Tests\Contracts\Test;

class OAuth2PasswordGrantTest extends Test {
    private function makeGrant(MockHandler $mock, string $clientSecret = ''): OAuth2PasswordGrant {
        return new OAuth2PasswordGrant(
            'client-id',
            $clientSecret,
            'https://provider.example.com/oauth2/token',
            null,
            new HttpClient(['handler' => HandlerStack::create($mock)])
        );
    }

    private static function tokenResponse(string $accessToken = 'at', ?string $refreshToken = 'rt', int $expiresIn = 3600): Response {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(array_filter([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'token_type' => 'Bearer',
        ])));
    }

    public function test_empty_username_is_rejected(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->makeGrant(new MockHandler([]))->fetchToken('', 'secret');
    }

    public function test_fetch_token_sends_password_grant_without_requiring_a_client_secret(): void {
        $mock = new MockHandler([self::tokenResponse()]);

        $token = $this->makeGrant($mock)->fetchToken("user\t4711", 'pass', ['openMasterdata']);

        $this->assertSame('at', $token->getAccessToken());
        $this->assertSame('rt', $token->getRefreshToken());
        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('POST', $request->getMethod());
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('password', $body['grant_type']);
        $this->assertSame("user\t4711", $body['username']);
        $this->assertSame('pass', $body['password']);
        $this->assertSame('openMasterdata', $body['scope']);
        $this->assertSame('client-id', $body['client_id']);
        $this->assertArrayNotHasKey('client_secret', $body);
    }

    public function test_client_secret_and_extra_params_are_sent_and_extra_params_win(): void {
        $mock = new MockHandler([self::tokenResponse()]);

        $this->makeGrant($mock, 'shh')->fetchToken('user', 'pass', [], ['grant_type' => 'client_credentials', 'audience' => 'omd']);

        parse_str((string) $mock->getLastRequest()?->getBody(), $body);
        $this->assertSame('client_credentials', $body['grant_type']);
        $this->assertSame('user', $body['username']);
        $this->assertSame('shh', $body['client_secret']);
        $this->assertSame('omd', $body['audience']);
    }

    public function test_refresh_token_sends_refresh_grant(): void {
        $mock = new MockHandler([self::tokenResponse('at2', null)]);

        $token = $this->makeGrant($mock)->refreshToken('rt');

        $this->assertSame('at2', $token->getAccessToken());
        $this->assertNull($token->getRefreshToken());
        parse_str((string) $mock->getLastRequest()?->getBody(), $body);
        $this->assertSame('refresh_token', $body['grant_type']);
        $this->assertSame('rt', $body['refresh_token']);
    }

    public function test_empty_refresh_token_is_rejected(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->makeGrant(new MockHandler([]))->refreshToken('');
    }

    public function test_bad_credentials_are_mapped_to_typed_exception(): void {
        $mock = new MockHandler([
            new Response(401, ['Content-Type' => 'application/json'], '{"error":"invalid_grant","error_description":"bad credentials"}'),
        ]);

        $this->expectException(UnauthorizedException::class);
        $this->makeGrant($mock)->fetchToken('user', 'wrong');
    }
}
