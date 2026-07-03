<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2Token.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Immutable value object for an OAuth2 token set.
 *
 * Persistence is the responsibility of the consuming application (see
 * OAuth2TokenStoreInterface); toArray()/fromArray() provide a stable,
 * serializable representation for that purpose.
 */
class OAuth2Token {
    protected string $accessToken;
    protected ?string $refreshToken;
    protected ?DateTimeImmutable $expiresAt;
    protected ?string $scope;
    protected string $tokenType;

    public function __construct(
        string $accessToken,
        ?string $refreshToken = null,
        ?DateTimeImmutable $expiresAt = null,
        ?string $scope = null,
        string $tokenType = 'Bearer'
    ) {
        if ($accessToken === '') {
            throw new InvalidArgumentException('Access token must not be empty');
        }
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresAt = $expiresAt;
        $this->scope = $scope;
        $this->tokenType = $tokenType;
    }

    public function getAccessToken(): string {
        return $this->accessToken;
    }

    public function getRefreshToken(): ?string {
        return $this->refreshToken;
    }

    public function getExpiresAt(): ?DateTimeImmutable {
        return $this->expiresAt;
    }

    public function getScope(): ?string {
        return $this->scope;
    }

    public function getTokenType(): string {
        return $this->tokenType;
    }

    /**
     * Whether the access token is expired (or about to expire).
     *
     * Tokens without expiry information never expire.
     *
     * @param int $leewaySeconds Safety margin: treat tokens as expired this
     *                           many seconds before their actual expiry
     */
    public function isExpired(int $leewaySeconds = 60): bool {
        if ($this->expiresAt === null) {
            return false;
        }
        return $this->expiresAt->getTimestamp() - $leewaySeconds <= time();
    }

    /**
     * Clone with a different refresh token. Used to carry over an existing
     * refresh token when the provider omits it in a refresh response.
     */
    public function withRefreshToken(?string $refreshToken): self {
        $clone = clone $this;
        $clone->refreshToken = $refreshToken;
        return $clone;
    }

    /**
     * Create a token from a standard OAuth2 token endpoint response
     * (RFC 6749 section 5.1).
     *
     * @param array<string, mixed> $payload Decoded JSON response
     */
    public static function fromResponse(array $payload): self {
        if (!isset($payload['access_token']) || !is_string($payload['access_token']) || $payload['access_token'] === '') {
            throw new InvalidArgumentException('Token response does not contain an access_token');
        }

        $expiresAt = null;
        if (isset($payload['expires_in']) && is_numeric($payload['expires_in'])) {
            $expiresAt = (new DateTimeImmutable)->modify(sprintf('+%d seconds', (int) $payload['expires_in']));
        }

        return new self(
            $payload['access_token'],
            isset($payload['refresh_token']) && is_string($payload['refresh_token']) ? $payload['refresh_token'] : null,
            $expiresAt,
            isset($payload['scope']) && is_string($payload['scope']) ? $payload['scope'] : null,
            isset($payload['token_type']) && is_string($payload['token_type']) && $payload['token_type'] !== '' ? $payload['token_type'] : 'Bearer'
        );
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_at: ?string, scope: ?string, token_type: string}
     */
    public function toArray(): array {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt?->format(DateTimeInterface::ATOM),
            'scope' => $this->scope,
            'token_type' => $this->tokenType,
        ];
    }

    /**
     * @param array<string, mixed> $data Representation produced by toArray()
     */
    public static function fromArray(array $data): self {
        if (!isset($data['access_token']) || !is_string($data['access_token']) || $data['access_token'] === '') {
            throw new InvalidArgumentException('Token data does not contain an access_token');
        }

        $expiresAt = null;
        if (isset($data['expires_at']) && is_string($data['expires_at']) && $data['expires_at'] !== '') {
            $expiresAt = new DateTimeImmutable($data['expires_at']);
        }

        return new self(
            $data['access_token'],
            isset($data['refresh_token']) && is_string($data['refresh_token']) ? $data['refresh_token'] : null,
            $expiresAt,
            isset($data['scope']) && is_string($data['scope']) ? $data['scope'] : null,
            isset($data['token_type']) && is_string($data['token_type']) && $data['token_type'] !== '' ? $data['token_type'] : 'Bearer'
        );
    }
}
