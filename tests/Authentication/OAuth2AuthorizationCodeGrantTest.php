<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2AuthorizationCodeGrantTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use APIToolkit\Exceptions\{ApiException, BadRequestException};
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Tests\Contracts\Test;

class OAuth2AuthorizationCodeGrantTest extends Test {
    private function makeGrant(?MockHandler $mock = null, ?string $redirectUri = 'https://app.example.com/callback'): OAuth2AuthorizationCodeGrant {
        $httpClient = $mock !== null
            ? new HttpClient(['handler' => HandlerStack::create($mock)])
            : null;

        return new OAuth2AuthorizationCodeGrant(
            'client-id',
            'client-secret',
            'https://provider.example.com/oauth/authorize',
            'https://provider.example.com/oauth/access_token',
            $redirectUri,
            null,
            $httpClient
        );
    }

    public function test_empty_credentials_are_rejected() {
        $this->expectException(InvalidArgumentException::class);
        new OAuth2AuthorizationCodeGrant('', 'secret', 'https://a', 'https://t');
    }

    public function test_authorization_url_contains_expected_parameters() {
        $grant = $this->makeGrant();

        $url = $grant->getAuthorizationUrl('state-123', ['data:read_write'], ['prompt' => 'consent']);

        $this->assertStringStartsWith('https://provider.example.com/oauth/authorize?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('state-123', $query['state']);
        $this->assertSame('data:read_write', $query['scope']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame('https://app.example.com/callback', $query['redirect_uri']);
    }

    public function test_authorization_url_without_redirect_uri_and_scopes() {
        $grant = $this->makeGrant(null, null);

        $url = $grant->getAuthorizationUrl('state-123');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertArrayNotHasKey('redirect_uri', $query);
        $this->assertArrayNotHasKey('scope', $query);
    }

    public function test_authorization_url_appends_to_existing_query() {
        $grant = new OAuth2AuthorizationCodeGrant(
            'client-id',
            'client-secret',
            'https://provider.example.com/oauth/authorize?tenant=common',
            'https://provider.example.com/oauth/access_token'
        );

        $url = $grant->getAuthorizationUrl('state-123');

        $this->assertStringContainsString('?tenant=common&', $url);
    }

    public function test_empty_state_is_rejected() {
        $grant = $this->makeGrant();

        $this->expectException(InvalidArgumentException::class);
        $grant->getAuthorizationUrl('');
    }

    public function test_exchange_authorization_code_returns_token() {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_token' => 'at',
                'refresh_token' => 'rt',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ])),
        ]);
        $grant = $this->makeGrant($mock);

        $token = $grant->exchangeAuthorizationCode('auth-code');

        $this->assertSame('at', $token->getAccessToken());
        $this->assertSame('rt', $token->getRefreshToken());

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('POST', $request->getMethod());
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('authorization_code', $body['grant_type']);
        $this->assertSame('auth-code', $body['code']);
        $this->assertSame('client-id', $body['client_id']);
        $this->assertSame('client-secret', $body['client_secret']);
        $this->assertSame('https://app.example.com/callback', $body['redirect_uri']);
    }

    public function test_refresh_token_sends_refresh_grant() {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_token' => 'new-at',
                'expires_in' => 3600,
            ])),
        ]);
        $grant = $this->makeGrant($mock);

        $token = $grant->refreshToken('rt-old');

        $this->assertSame('new-at', $token->getAccessToken());
        $this->assertNull($token->getRefreshToken());

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('refresh_token', $body['grant_type']);
        $this->assertSame('rt-old', $body['refresh_token']);
    }

    public function test_token_endpoint_error_is_mapped_to_typed_exception() {
        $mock = new MockHandler([
            new Response(400, ['Content-Type' => 'application/json'], (string) json_encode([
                'error' => 'invalid_grant',
            ])),
        ]);
        $grant = $this->makeGrant($mock);

        $this->expectException(BadRequestException::class);
        $grant->exchangeAuthorizationCode('expired-code');
    }

    public function test_unexpected_payload_throws_api_exception() {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"foo":"bar"}'),
        ]);
        $grant = $this->makeGrant($mock);

        $this->expectException(ApiException::class);
        $grant->exchangeAuthorizationCode('auth-code');
    }

    public function test_empty_code_is_rejected() {
        $grant = $this->makeGrant();

        $this->expectException(InvalidArgumentException::class);
        $grant->exchangeAuthorizationCode('');
    }

    public function test_empty_refresh_token_is_rejected() {
        $grant = $this->makeGrant();

        $this->expectException(InvalidArgumentException::class);
        $grant->refreshToken('');
    }
}
