<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2TokenTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Authentication;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\Contracts\Test;

class OAuth2TokenTest extends Test {
    public function test_empty_access_token_is_rejected() {
        $this->expectException(InvalidArgumentException::class);
        new OAuth2Token('');
    }

    public function test_token_without_expiry_never_expires() {
        $token = new OAuth2Token('access');

        $this->assertFalse($token->isExpired());
        $this->assertFalse($token->isExpired(86400));
    }

    public function test_leeway_is_applied() {
        $token = new OAuth2Token('access', null, new DateTimeImmutable('+30 seconds'));

        $this->assertTrue($token->isExpired(60));
        $this->assertFalse($token->isExpired(0));
    }

    public function test_expired_token_is_expired() {
        $token = new OAuth2Token('access', null, new DateTimeImmutable('-10 minutes'));

        $this->assertTrue($token->isExpired(0));
    }

    public function test_from_response_with_full_payload() {
        $token = OAuth2Token::fromResponse([
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires_in' => 3600,
            'scope' => 'data:read_write',
            'token_type' => 'Bearer',
        ]);

        $this->assertSame('at', $token->getAccessToken());
        $this->assertSame('rt', $token->getRefreshToken());
        $this->assertSame('data:read_write', $token->getScope());
        $this->assertSame('Bearer', $token->getTokenType());
        $this->assertNotNull($token->getExpiresAt());
        $this->assertEqualsWithDelta(time() + 3600, $token->getExpiresAt()->getTimestamp(), 5);
    }

    public function test_from_response_with_minimal_payload_uses_defaults() {
        $token = OAuth2Token::fromResponse(['access_token' => 'at']);

        $this->assertSame('at', $token->getAccessToken());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getExpiresAt());
        $this->assertNull($token->getScope());
        $this->assertSame('Bearer', $token->getTokenType());
    }

    public function test_from_response_without_access_token_is_rejected() {
        $this->expectException(InvalidArgumentException::class);
        OAuth2Token::fromResponse(['token_type' => 'Bearer']);
    }

    public function test_array_round_trip() {
        $token = new OAuth2Token('at', 'rt', new DateTimeImmutable('2026-07-03T12:00:00+00:00'), 'scope-a', 'Bearer');

        $restored = OAuth2Token::fromArray($token->toArray());

        $this->assertSame($token->getAccessToken(), $restored->getAccessToken());
        $this->assertSame($token->getRefreshToken(), $restored->getRefreshToken());
        $this->assertSame($token->getScope(), $restored->getScope());
        $this->assertSame($token->getTokenType(), $restored->getTokenType());
        $this->assertNotNull($restored->getExpiresAt());
        $this->assertSame(
            $token->getExpiresAt()->getTimestamp(),
            $restored->getExpiresAt()->getTimestamp()
        );
    }

    public function test_from_array_without_access_token_is_rejected() {
        $this->expectException(InvalidArgumentException::class);
        OAuth2Token::fromArray(['refresh_token' => 'rt']);
    }

    public function test_with_refresh_token_returns_modified_clone() {
        $token = new OAuth2Token('at', null);
        $clone = $token->withRefreshToken('rt');

        $this->assertNull($token->getRefreshToken());
        $this->assertSame('rt', $clone->getRefreshToken());
        $this->assertSame('at', $clone->getAccessToken());
    }
}
