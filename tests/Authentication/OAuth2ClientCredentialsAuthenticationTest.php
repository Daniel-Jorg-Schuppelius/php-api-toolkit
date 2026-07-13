<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2ClientCredentialsAuthenticationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\{InMemoryTokenStore, OAuth2ClientCredentialsAuthentication, OAuth2ClientCredentialsGrant, OAuth2Token};
use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use APIToolkit\Exceptions\UnauthorizedException;
use DateTimeImmutable;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Tests\Contracts\Test;

class OAuth2ClientCredentialsAuthenticationTest extends Test {
    private function makeGrant(MockHandler $mock): OAuth2ClientCredentialsGrant {
        return new OAuth2ClientCredentialsGrant(
            'client-id',
            'client-secret',
            'https://provider.example.com/oauth/token',
            null,
            new HttpClient(['handler' => HandlerStack::create($mock)])
        );
    }

    private function makeApiClient(MockHandler $mock): ClientAbstract {
        $httpClient = new HttpClient(['handler' => HandlerStack::create($mock)]);

        return new class('https://api.example.com', null, false, $httpClient) extends ClientAbstract {};
    }

    private static function tokenResponse(string $accessToken, int $expiresIn = 3600): Response {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ]));
    }

    public function test_first_use_fetches_token_and_persists_it() {
        $mock = new MockHandler([self::tokenResponse('at-1')]);
        $store = new InMemoryTokenStore;
        $auth = new OAuth2ClientCredentialsAuthentication($this->makeGrant($mock), $store, ['read'], 60, ['X-Extra' => 'yes']);

        $this->assertTrue($auth->isValid());
        $this->assertSame('OAuth2', $auth->getType());
        $this->assertSame(
            ['Authorization' => 'Bearer at-1', 'X-Extra' => 'yes'],
            $auth->getAuthHeaders()
        );

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('read', $body['scope']);

        $persisted = $store->load();
        $this->assertNotNull($persisted);
        $this->assertSame('at-1', $persisted->getAccessToken());
    }

    public function test_fresh_token_is_not_fetched_again() {
        $mock = new MockHandler([]);
        $store = new InMemoryTokenStore(new OAuth2Token('at', null, new DateTimeImmutable('+2 hours')));
        $auth = new OAuth2ClientCredentialsAuthentication($this->makeGrant($mock), $store);

        $headers = $auth->getAuthHeaders();

        $this->assertSame('Bearer at', $headers['Authorization']);
        $this->assertNull($mock->getLastRequest(), 'No token endpoint call expected for a fresh token');
    }

    public function test_expired_token_triggers_refetch() {
        $mock = new MockHandler([self::tokenResponse('at-new')]);
        $store = new InMemoryTokenStore(new OAuth2Token('at-old', null, new DateTimeImmutable('-10 minutes')));
        $auth = new OAuth2ClientCredentialsAuthentication($this->makeGrant($mock), $store);

        $headers = $auth->getAuthHeaders();

        $this->assertSame('Bearer at-new', $headers['Authorization']);

        $persisted = $store->load();
        $this->assertNotNull($persisted);
        $this->assertSame('at-new', $persisted->getAccessToken());
        $this->assertFalse($persisted->isExpired());
    }

    public function test_expiry_leeway_is_respected() {
        // Token is still valid for 30 seconds — with a 60 second leeway it counts as expired.
        $mock = new MockHandler([self::tokenResponse('at-new')]);
        $store = new InMemoryTokenStore(new OAuth2Token('at-soon-stale', null, new DateTimeImmutable('+30 seconds')));
        $auth = new OAuth2ClientCredentialsAuthentication($this->makeGrant($mock), $store, [], 60);

        $headers = $auth->getAuthHeaders();

        $this->assertSame('Bearer at-new', $headers['Authorization']);
    }

    public function test_injected_token_store_hook_is_used() {
        $store = new class implements OAuth2TokenStoreInterface {
            public int $loads = 0;
            public int $saves = 0;
            private ?OAuth2Token $token = null;

            public function load(): ?OAuth2Token {
                $this->loads++;
                return $this->token;
            }

            public function save(OAuth2Token $token): void {
                $this->saves++;
                $this->token = $token;
            }

            public function clear(): void {
                $this->token = null;
            }
        };

        $mock = new MockHandler([self::tokenResponse('at-1')]);
        $auth = new OAuth2ClientCredentialsAuthentication($this->makeGrant($mock), $store);

        $auth->getAuthHeaders(); // fetch + save
        $auth->getAuthHeaders(); // served from the store

        $this->assertSame(2, $store->loads);
        $this->assertSame(1, $store->saves);
        $this->assertSame(0, $mock->count(), 'Exactly one token call expected');
    }

    public function test_unauthorized_discards_token_and_retries_exactly_once() {
        $tokenEndpoint = new MockHandler([
            self::tokenResponse('at-revoked'),
            self::tokenResponse('at-fresh'),
        ]);
        $auth = new OAuth2ClientCredentialsAuthentication($this->makeGrant($tokenEndpoint));

        $api = new MockHandler([
            new Response(401),
            new Response(200, [], 'ok'),
        ]);
        $client = $this->makeApiClient($api);
        $client->setAuthentication($auth);

        $response = $client->get('/tasks');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $tokenEndpoint->count(), 'Exactly two token calls expected: initial fetch + refetch after 401');

        $request = $api->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('Bearer at-fresh', $request->getHeaderLine('Authorization'));
    }

    public function test_second_unauthorized_after_refetch_propagates() {
        $tokenEndpoint = new MockHandler([
            self::tokenResponse('at-1'),
            self::tokenResponse('at-2'),
        ]);
        $auth = new OAuth2ClientCredentialsAuthentication($this->makeGrant($tokenEndpoint));

        $api = new MockHandler([
            new Response(401),
            new Response(401),
        ]);
        $client = $this->makeApiClient($api);
        $client->setAuthentication($auth);

        $this->expectException(UnauthorizedException::class);
        $client->get('/tasks');
    }

    public function test_failed_refetch_lets_original_unauthorized_propagate() {
        $tokenEndpoint = new MockHandler([
            self::tokenResponse('at-revoked'),
            new Response(401, ['Content-Type' => 'application/json'], (string) json_encode(['error' => 'invalid_client'])),
        ]);
        $auth = new OAuth2ClientCredentialsAuthentication($this->makeGrant($tokenEndpoint));

        $api = new MockHandler([new Response(401)]);
        $client = $this->makeApiClient($api);
        $client->setAuthentication($auth);

        $this->expectException(UnauthorizedException::class);
        $client->get('/tasks');
    }
}
