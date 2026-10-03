<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2PasswordAuthentication.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use APIToolkit\Contracts\Interfaces\API\{OAuth2TokenStoreInterface, RefreshableAuthenticationInterface};
use APIToolkit\Exceptions\ApiException;

/**
 * Bearer authentication backed by the password grant: logs in with the stored
 * user credentials when no token exists, renews an expired token through its
 * refresh token and falls back to a fresh login when the provider issues no
 * refresh token or rejects it.
 */
class OAuth2PasswordAuthentication implements RefreshableAuthenticationInterface {
    protected OAuth2PasswordGrant $grant;
    protected OAuth2TokenStoreInterface $tokenStore;
    protected string $username;
    protected string $password;
    /** @var array<int, string> */
    protected array $scopes;
    protected int $expiryLeeway;
    /** @var array<string, string> */
    protected array $additionalHeaders;
    /** @var array<string, string> */
    protected array $additionalTokenParams;

    /**
     * @param OAuth2PasswordGrant $grant Grant used to log in and refresh
     * @param string $username Resource owner's user name as the provider expects it
     * @param string $password Resource owner's password
     * @param OAuth2TokenStoreInterface|null $tokenStore Token persistence (default: in-memory)
     * @param array<int, string> $scopes Scopes requested with every login
     * @param int $expiryLeeway Seconds before actual expiry a token is treated as expired
     * @param array<string, string> $additionalHeaders Optional additional headers to include
     * @param array<string, string> $additionalTokenParams Extra provider-specific form params sent with every login
     */
    public function __construct(
        OAuth2PasswordGrant $grant,
        string $username,
        #[\SensitiveParameter]
        string $password,
        ?OAuth2TokenStoreInterface $tokenStore = null,
        array $scopes = [],
        int $expiryLeeway = 60,
        array $additionalHeaders = [],
        array $additionalTokenParams = []
    ) {
        $this->grant = $grant;
        $this->username = $username;
        $this->password = $password;
        $this->tokenStore = $tokenStore ?? new InMemoryTokenStore;
        $this->scopes = $scopes;
        $this->expiryLeeway = $expiryLeeway;
        $this->additionalHeaders = $additionalHeaders;
        $this->additionalTokenParams = $additionalTokenParams;
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
     * Always true: the credentials allow a new login at any time; token
     * endpoint failures surface as typed exceptions.
     */
    public function isValid(): bool {
        return true;
    }

    /**
     * Force a new login (RefreshableAuthenticationInterface).
     *
     * Called by ClientAbstract after a 401: the stored token is discarded and
     * replaced by a freshly fetched one, then the request is retried exactly
     * once. Never throws, so the original 401 can propagate unmasked.
     */
    public function refresh(): bool {
        $this->tokenStore->clear();

        try {
            $token = $this->login();
        } catch (ApiException) {
            return false;
        }

        $this->tokenStore->save($token);

        return true;
    }

    /**
     * Return a usable (non-expired) token: the stored one, its refreshed
     * successor, or the result of a fresh login.
     */
    protected function freshToken(): OAuth2Token {
        $token = $this->tokenStore->load();

        if ($token !== null && !$token->isExpired($this->expiryLeeway)) {
            return $token;
        }

        $refreshToken = $token?->getRefreshToken();
        $fresh = null;

        if ($refreshToken !== null) {
            try {
                $fresh = $this->grant->refreshToken($refreshToken);
                if ($fresh->getRefreshToken() === null) {
                    $fresh = $fresh->withRefreshToken($refreshToken);
                }
            } catch (ApiException) {
                // A rejected refresh token is not fatal: the credentials are still at hand.
                $fresh = null;
            }
        }

        $fresh ??= $this->login();
        $this->tokenStore->save($fresh);

        return $fresh;
    }

    protected function login(): OAuth2Token {
        return $this->grant->fetchToken($this->username, $this->password, $this->scopes, $this->additionalTokenParams);
    }
}
