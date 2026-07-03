<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2BearerAuthenticationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\{InMemoryTokenStore, OAuth2AuthorizationCodeGrant, OAuth2BearerAuthentication, OAuth2Token};
use APIToolkit\Exceptions\UnauthorizedException;
use DateTimeImmutable;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Tests\Contracts\Test;

class OAuth2BearerAuthenticationTest extends Test {
    private function makeGrant(MockHandler $mock): OAuth2AuthorizationCodeGrant {
        return new OAuth2AuthorizationCodeGrant(
            'client-id',
            'client-secret',
            'https://provider.example.com/oauth/authorize',
            'https://provider.example.com/oauth/access_token',
            null,
            null,
            new HttpClient(['handler' => HandlerStack::create($mock)])
        );
    }

    public function test_without_stored_token_authentication_is_invalid() {
        $auth = new OAuth2BearerAuthentication(new InMemoryTokenStore);

        $this->assertFalse($auth->isValid());

        $this->expectException(UnauthorizedException::class);
        $auth->getAuthHeaders();
    }

    public function test_valid_token_produces_bearer_header() {
        $store = new InMemoryTokenStore(new OAuth2Token('at'));
        $auth = new OAuth2BearerAuthentication($store, null, 60, ['X-Extra' => 'yes']);

        $this->assertTrue($auth->isValid());
        $this->assertSame('OAuth2', $auth->getType());
        $this->assertSame(
            ['Authorization' => 'Bearer at', 'X-Extra' => 'yes'],
            $auth->getAuthHeaders()
        );
    }

    public function test_expired_token_without_refresh_possibility_is_invalid() {
        $store = new InMemoryTokenStore(new OAuth2Token('at', null, new DateTimeImmutable('-10 minutes')));
        $auth = new OAuth2BearerAuthentication($store);

        $this->assertFalse($auth->isValid());

        $this->expectException(UnauthorizedException::class);
        $auth->getAuthHeaders();
    }

    public function test_expired_token_is_refreshed_and_persisted() {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_token' => 'new-at',
                'expires_in' => 3600,
            ])),
        ]);
        $store = new InMemoryTokenStore(new OAuth2Token('old-at', 'rt', new DateTimeImmutable('-10 minutes')));
        $auth = new OAuth2BearerAuthentication($store, $this->makeGrant($mock));

        $this->assertTrue($auth->isValid());

        $headers = $auth->getAuthHeaders();

        $this->assertSame('Bearer new-at', $headers['Authorization']);

        $persisted = $store->load();
        $this->assertNotNull($persisted);
        $this->assertSame('new-at', $persisted->getAccessToken());
        // refresh token is carried over when the provider omits it
        $this->assertSame('rt', $persisted->getRefreshToken());
        $this->assertFalse($persisted->isExpired());
    }

    public function test_fresh_token_is_not_refreshed() {
        $mock = new MockHandler([]);
        $store = new InMemoryTokenStore(new OAuth2Token('at', 'rt', new DateTimeImmutable('+2 hours')));
        $auth = new OAuth2BearerAuthentication($store, $this->makeGrant($mock));

        $headers = $auth->getAuthHeaders();

        $this->assertSame('Bearer at', $headers['Authorization']);
        $this->assertNull($mock->getLastRequest(), 'No token endpoint call expected for a fresh token');
    }

    public function test_token_store_clear_invalidates_authentication() {
        $store = new InMemoryTokenStore(new OAuth2Token('at'));
        $auth = new OAuth2BearerAuthentication($store);

        $this->assertTrue($auth->isValid());

        $store->clear();

        $this->assertFalse($auth->isValid());
    }
}
