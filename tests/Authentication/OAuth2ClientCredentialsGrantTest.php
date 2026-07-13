<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2ClientCredentialsGrantTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsGrant;
use APIToolkit\Exceptions\{ApiException, UnauthorizedException};
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use RuntimeException;
use Tests\Contracts\Test;

class OAuth2ClientCredentialsGrantTest extends Test {
    private function makeGrant(?MockHandler $mock = null, string $clientSecret = 'client-secret'): OAuth2ClientCredentialsGrant {
        $httpClient = $mock !== null
            ? new HttpClient(['handler' => HandlerStack::create($mock)])
            : null;

        return new OAuth2ClientCredentialsGrant(
            'client-id',
            $clientSecret,
            'https://provider.example.com/oauth/token',
            null,
            $httpClient
        );
    }

    private static function tokenResponse(string $accessToken = 'at', int $expiresIn = 3600): Response {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
            'token_type' => 'Bearer',
        ]));
    }

    public function test_empty_client_id_is_rejected() {
        $this->expectException(InvalidArgumentException::class);
        new OAuth2ClientCredentialsGrant('', 'secret', 'https://t');
    }

    public function test_empty_token_url_is_rejected() {
        $this->expectException(InvalidArgumentException::class);
        new OAuth2ClientCredentialsGrant('client-id', 'secret', '');
    }

    public function test_fetch_token_sends_credentials_in_form_body_by_default() {
        $mock = new MockHandler([self::tokenResponse()]);
        $grant = $this->makeGrant($mock);

        $token = $grant->fetchToken();

        $this->assertSame('at', $token->getAccessToken());
        $this->assertNull($token->getRefreshToken());
        $this->assertFalse($token->isExpired());

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('', $request->getHeaderLine('Authorization'));
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('client_credentials', $body['grant_type']);
        $this->assertSame('client-id', $body['client_id']);
        $this->assertSame('client-secret', $body['client_secret']);
    }

    public function test_basic_client_auth_moves_credentials_to_header() {
        $mock = new MockHandler([self::tokenResponse()]);
        $grant = $this->makeGrant($mock);
        $grant->setTokenAuthMethod(OAuth2ClientCredentialsGrant::AUTH_METHOD_BASIC);

        $grant->fetchToken();

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('Basic ' . base64_encode('client-id:client-secret'), $request->getHeaderLine('Authorization'));
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('client_credentials', $body['grant_type']);
        $this->assertArrayNotHasKey('client_id', $body);
        $this->assertArrayNotHasKey('client_secret', $body);
    }

    public function test_scopes_and_extra_params_are_passed() {
        $mock = new MockHandler([self::tokenResponse()]);
        $grant = $this->makeGrant($mock);

        $grant->fetchToken(['read', 'write'], ['audience' => 'https://api.example.com', 'grant_type' => 'must-not-win']);

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('read write', $body['scope']);
        $this->assertSame('https://api.example.com', $body['audience']);
        // extraParams must never override the grant type
        $this->assertSame('client_credentials', $body['grant_type']);
    }

    public function test_without_scopes_no_scope_parameter_is_sent() {
        $mock = new MockHandler([self::tokenResponse()]);
        $grant = $this->makeGrant($mock);

        $grant->fetchToken();

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str((string) $request->getBody(), $body);
        $this->assertArrayNotHasKey('scope', $body);
    }

    public function test_invalid_client_is_mapped_to_typed_exception() {
        $mock = new MockHandler([
            new Response(401, ['Content-Type' => 'application/json'], (string) json_encode([
                'error' => 'invalid_client',
            ])),
        ]);
        $grant = $this->makeGrant($mock);

        $this->expectException(UnauthorizedException::class);
        $grant->fetchToken();
    }

    public function test_unexpected_payload_throws_api_exception() {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"foo":"bar"}'),
        ]);
        $grant = $this->makeGrant($mock);

        $this->expectException(ApiException::class);
        $grant->fetchToken();
    }

    public function test_missing_client_secret_without_assertion_is_rejected() {
        $grant = $this->makeGrant(new MockHandler([self::tokenResponse()]), '');

        $this->expectException(RuntimeException::class);
        $grant->fetchToken();
    }
}
