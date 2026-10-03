<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2PasswordAuthenticationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\{InMemoryTokenStore, OAuth2PasswordAuthentication, OAuth2PasswordGrant, OAuth2Token};
use DateTimeImmutable;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Tests\Contracts\Test;

class OAuth2PasswordAuthenticationTest extends Test {
    private function makeAuth(MockHandler $mock, InMemoryTokenStore $store): OAuth2PasswordAuthentication {
        $grant = new OAuth2PasswordGrant('client-id', '', 'https://provider.example.com/oauth2/token', null, new HttpClient(['handler' => HandlerStack::create($mock)]));

        return new OAuth2PasswordAuthentication($grant, 'user', 'pass', $store, ['openMasterdata']);
    }

    private static function tokenResponse(string $accessToken, ?string $refreshToken = 'rt'): Response {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(array_filter([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ])));
    }

    public function test_logs_in_once_and_reuses_the_stored_token(): void {
        $mock = new MockHandler([self::tokenResponse('at')]);
        $auth = $this->makeAuth($mock, new InMemoryTokenStore);

        $first = $auth->getAuthHeaders();
        $second = $auth->getAuthHeaders();
        $this->assertSame(['Authorization' => 'Bearer at'], $first);
        $this->assertSame($first, $second);
        $this->assertSame(0, $mock->count(), 'nur ein Login');
        parse_str((string) $mock->getLastRequest()?->getBody(), $body);
        $this->assertSame('password', $body['grant_type']);
    }

    public function test_expired_token_is_renewed_through_the_refresh_token(): void {
        $store = new InMemoryTokenStore;
        $store->save(new OAuth2Token('old', 'rt', new DateTimeImmutable('-1 minute')));
        $mock = new MockHandler([self::tokenResponse('fresh', null)]);

        $headers = $this->makeAuth($mock, $store)->getAuthHeaders();

        $this->assertSame('Bearer fresh', $headers['Authorization']);
        parse_str((string) $mock->getLastRequest()?->getBody(), $body);
        $this->assertSame('refresh_token', $body['grant_type']);
        $this->assertSame('rt', $store->load()?->getRefreshToken(), 'Refresh-Token bleibt, wenn der Server keins liefert');
    }

    public function test_rejected_refresh_falls_back_to_a_new_login(): void {
        $store = new InMemoryTokenStore;
        $store->save(new OAuth2Token('old', 'stale', new DateTimeImmutable('-1 minute')));
        $mock = new MockHandler([
            new Response(400, ['Content-Type' => 'application/json'], '{"error":"invalid_grant"}'),
            self::tokenResponse('relogin'),
        ]);

        $headers = $this->makeAuth($mock, $store)->getAuthHeaders();

        $this->assertSame('Bearer relogin', $headers['Authorization']);
        parse_str((string) $mock->getLastRequest()?->getBody(), $body);
        $this->assertSame('password', $body['grant_type']);
    }

    public function test_refresh_after_401_replaces_the_token_or_reports_failure(): void {
        $store = new InMemoryTokenStore;
        $store->save(new OAuth2Token('revoked', null, new DateTimeImmutable('+1 hour')));
        $mock = new MockHandler([
            self::tokenResponse('again'),
            new Response(401, ['Content-Type' => 'application/json'], '{"error":"invalid_grant"}'),
        ]);
        $auth = $this->makeAuth($mock, $store);

        $this->assertTrue($auth->refresh());
        $this->assertSame('again', $store->load()?->getAccessToken());
        $this->assertFalse($auth->refresh());
        $this->assertNull($store->load());
    }
}
