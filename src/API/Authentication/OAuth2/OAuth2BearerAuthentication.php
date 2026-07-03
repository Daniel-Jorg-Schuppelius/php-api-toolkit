<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2BearerAuthentication.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use APIToolkit\Contracts\Interfaces\API\{AuthenticationInterface, OAuth2TokenStoreInterface};
use APIToolkit\Exceptions\UnauthorizedException;

/**
 * Bearer authentication backed by a stored OAuth2 token set.
 *
 * Loads the current token from the injected store on every request and,
 * when it is expired and a grant plus refresh token are available,
 * refreshes it transparently and persists the result through the store.
 *
 * getAuthHeaders() throws UnauthorizedException when no usable token can
 * be obtained; isValid() reports whether an attempt makes sense at all.
 */
class OAuth2BearerAuthentication implements AuthenticationInterface {
    protected OAuth2TokenStoreInterface $tokenStore;
    protected ?OAuth2AuthorizationCodeGrant $grant;
    protected int $expiryLeeway;
    /** @var array<string, string> */
    protected array $additionalHeaders;

    /**
     * @param OAuth2TokenStoreInterface $tokenStore Application-side token persistence
     * @param OAuth2AuthorizationCodeGrant|null $grant Grant used for automatic refresh (optional)
     * @param int $expiryLeeway Seconds before actual expiry a token is treated as expired
     * @param array<string, string> $additionalHeaders Optional additional headers to include
     */
    public function __construct(
        OAuth2TokenStoreInterface $tokenStore,
        ?OAuth2AuthorizationCodeGrant $grant = null,
        int $expiryLeeway = 60,
        array $additionalHeaders = []
    ) {
        $this->tokenStore = $tokenStore;
        $this->grant = $grant;
        $this->expiryLeeway = $expiryLeeway;
        $this->additionalHeaders = $additionalHeaders;
    }

    public function getAuthHeaders(): array {
        $token = $this->freshToken();

        return array_merge(
            ['Authorization' => $token->getTokenType() . ' ' . $token->getAccessToken()],
            $this->additionalHeaders
        );
    }

    public function getType(): string {
        return 'OAuth2';
    }

    public function isValid(): bool {
        $token = $this->tokenStore->load();

        if ($token === null) {
            return false;
        }

        if (!$token->isExpired($this->expiryLeeway)) {
            return true;
        }

        return $this->grant !== null && $token->getRefreshToken() !== null;
    }

    /**
     * Return a usable (non-expired) token, refreshing it if necessary.
     *
     * @throws UnauthorizedException When no token is stored or the stored
     *                               token is expired and cannot be refreshed
     */
    protected function freshToken(): OAuth2Token {
        $token = $this->tokenStore->load();

        if ($token === null) {
            throw new UnauthorizedException('No OAuth2 token available', 401);
        }

        if (!$token->isExpired($this->expiryLeeway)) {
            return $token;
        }

        $refreshToken = $token->getRefreshToken();

        if ($this->grant === null || $refreshToken === null) {
            throw new UnauthorizedException('OAuth2 token is expired and cannot be refreshed', 401);
        }

        $refreshed = $this->grant->refreshToken($refreshToken);

        if ($refreshed->getRefreshToken() === null) {
            $refreshed = $refreshed->withRefreshToken($refreshToken);
        }

        $this->tokenStore->save($refreshed);

        return $refreshed;
    }
}
