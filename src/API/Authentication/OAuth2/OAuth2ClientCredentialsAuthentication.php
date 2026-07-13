<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2ClientCredentialsAuthentication.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use APIToolkit\Contracts\Interfaces\API\{OAuth2TokenStoreInterface, RefreshableAuthenticationInterface};
use APIToolkit\Exceptions\ApiException;

/**
 * Bearer authentication backed by the OAuth2 Client Credentials grant.
 *
 * Loads the current token from the injected store on every request and
 * fetches a fresh one when none is stored or the stored one is expired
 * (with leeway). There is no refresh token in this grant — "refresh"
 * always means a new client_credentials token request.
 *
 * Implements RefreshableAuthenticationInterface, so ClientAbstract discards
 * the token and retries exactly once after a 401 (server-side invalidation).
 *
 * The token store defaults to a per-instance InMemoryTokenStore; inject an
 * application-side implementation (e.g. encrypted per-tenant storage) to
 * share tokens across processes.
 */
class OAuth2ClientCredentialsAuthentication implements RefreshableAuthenticationInterface {
    protected OAuth2ClientCredentialsGrant $grant;
    protected OAuth2TokenStoreInterface $tokenStore;
    /** @var array<int, string> */
    protected array $scopes;
    protected int $expiryLeeway;
    /** @var array<string, string> */
    protected array $additionalHeaders;

    /**
     * @param OAuth2ClientCredentialsGrant $grant Grant used to fetch tokens
     * @param OAuth2TokenStoreInterface|null $tokenStore Token persistence (default: in-memory)
     * @param array<int, string> $scopes Scopes requested with every token fetch
     * @param int $expiryLeeway Seconds before actual expiry a token is treated as expired
     * @param array<string, string> $additionalHeaders Optional additional headers to include
     */
    public function __construct(
        OAuth2ClientCredentialsGrant $grant,
        ?OAuth2TokenStoreInterface $tokenStore = null,
        array $scopes = [],
        int $expiryLeeway = 60,
        array $additionalHeaders = []
    ) {
        $this->grant = $grant;
        $this->tokenStore = $tokenStore ?? new InMemoryTokenStore;
        $this->scopes = $scopes;
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

    /**
     * Always true: the grant can fetch a token at any time without user
     * interaction; token endpoint failures surface as typed exceptions.
     */
    public function isValid(): bool {
        return true;
    }

    /**
     * Force-fetch a new token (RefreshableAuthenticationInterface).
     *
     * Called by ClientAbstract after a 401: the stored token is discarded
     * and replaced by a freshly fetched one, then the request is retried
     * exactly once. Never throws: returns false when the token endpoint
     * rejects the fetch, so the original 401 can propagate unmasked.
     */
    public function refresh(): bool {
        $this->tokenStore->clear();

        try {
            $token = $this->grant->fetchToken($this->scopes);
        } catch (ApiException) {
            return false;
        }

        $this->tokenStore->save($token);

        return true;
    }

    /**
     * Return a usable (non-expired) token, fetching a new one if necessary.
     */
    protected function freshToken(): OAuth2Token {
        $token = $this->tokenStore->load();

        if ($token !== null && !$token->isExpired($this->expiryLeeway)) {
            return $token;
        }

        $token = $this->grant->fetchToken($this->scopes);
        $this->tokenStore->save($token);

        return $token;
    }
}
