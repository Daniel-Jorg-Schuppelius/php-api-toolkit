<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2GrantExtensionsTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use RuntimeException;
use Tests\Contracts\Test;

class OAuth2GrantExtensionsTest extends Test {
    private function makeGrant(?MockHandler $mock = null): OAuth2AuthorizationCodeGrant {
        $httpClient = $mock !== null
            ? new HttpClient(['handler' => HandlerStack::create($mock)])
            : null;

        return new OAuth2AuthorizationCodeGrant(
            'client-id',
            'client-secret',
            'https://provider.example.com/oauth/authorize',
            'https://provider.example.com/oauth/access_token',
            'https://app.example.com/callback',
            null,
            $httpClient
        );
    }

    public function test_pkce_verifier_has_valid_format() {
        $verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]{43,128}$/', $verifier);
        $this->assertNotSame($verifier, OAuth2AuthorizationCodeGrant::generatePkceVerifier());
    }

    public function test_pkce_challenge_is_rfc7636_s256() {
        // Test vector from RFC 7636 appendix B
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

        $this->assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            OAuth2AuthorizationCodeGrant::pkceChallenge($verifier)
        );
    }

    public function test_authorization_url_carries_pkce_challenge() {
        $grant = $this->makeGrant();
        $verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();

        $url = $grant->getAuthorizationUrl('state-1', [], [], $verifier);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(OAuth2AuthorizationCodeGrant::pkceChallenge($verifier), $query['code_challenge']);
        $this->assertSame('S256', $query['code_challenge_method']);
    }

    public function test_exchange_sends_pkce_verifier() {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['access_token' => 'at'])),
        ]);
        $grant = $this->makeGrant($mock);

        $grant->exchangeAuthorizationCode('code-1', 'my-verifier-my-verifier-my-verifier-my-verif');

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('my-verifier-my-verifier-my-verifier-my-verif', $body['code_verifier']);
    }

    public function test_basic_client_auth_moves_credentials_to_header() {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['access_token' => 'at'])),
        ]);
        $grant = $this->makeGrant($mock);
        $grant->setTokenAuthMethod(OAuth2AuthorizationCodeGrant::AUTH_METHOD_BASIC);

        $grant->exchangeAuthorizationCode('code-1');

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('Basic ' . base64_encode('client-id:client-secret'), $request->getHeaderLine('Authorization'));
        parse_str((string) $request->getBody(), $body);
        $this->assertArrayNotHasKey('client_id', $body);
        $this->assertArrayNotHasKey('client_secret', $body);
    }

    public function test_unknown_token_auth_method_is_rejected() {
        $grant = $this->makeGrant();

        $this->expectException(InvalidArgumentException::class);
        $grant->setTokenAuthMethod('mtls');
    }

    public function test_revoke_token_posts_to_revocation_endpoint() {
        $mock = new MockHandler([new Response(200)]);
        $grant = $this->makeGrant($mock);
        $grant->setRevocationUrl('https://provider.example.com/oauth/revoke');

        $grant->revokeToken('token-x', 'refresh_token');

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('/oauth/revoke', $request->getUri()->getPath());
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('token-x', $body['token']);
        $this->assertSame('refresh_token', $body['token_type_hint']);
        $this->assertSame('client-id', $body['client_id']);
    }

    public function test_revoke_token_without_configured_url_throws() {
        $grant = $this->makeGrant();

        $this->expectException(RuntimeException::class);
        $grant->revokeToken('token-x');
    }
}
