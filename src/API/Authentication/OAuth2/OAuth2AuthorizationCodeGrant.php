<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2AuthorizationCodeGrant.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use GuzzleHttp\Client as HttpClient;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Provider-neutral OAuth2 Authorization Code grant (RFC 6749 section 4.1).
 *
 * Builds the authorization URL for the browser redirect and exchanges
 * authorization codes / refresh tokens at the token endpoint. State
 * generation, state validation and token persistence remain the
 * responsibility of the consuming application.
 *
 * Token endpoint mechanics (client authentication, error mapping,
 * throttling, retries incl. Retry-After) are shared via OAuth2GrantAbstract.
 */
class OAuth2AuthorizationCodeGrant extends OAuth2GrantAbstract {
    protected string $authorizeUrl;
    protected ?string $redirectUri;
    protected ?string $revocationUrl = null;

    /**
     * @param string $clientId OAuth2 client id
     * @param string $clientSecret OAuth2 client secret
     * @param string $authorizeUrl Full authorization endpoint URL (browser redirect target)
     * @param string $tokenUrl Full token endpoint URL
     * @param string|null $redirectUri Registered redirect URI (omitted from requests when null)
     * @param LoggerInterface|null $logger PSR-3 logger instance
     * @param HttpClient|null $httpClient Optional pre-configured Guzzle client (e.g. MockHandler in tests)
     */
    public function __construct(
        string $clientId,
        string $clientSecret,
        string $authorizeUrl,
        string $tokenUrl,
        ?string $redirectUri = null,
        ?LoggerInterface $logger = null,
        ?HttpClient $httpClient = null
    ) {
        if ($clientId === '' || $clientSecret === '') {
            throw new InvalidArgumentException('Client id and client secret must not be empty');
        }
        if ($authorizeUrl === '' || $tokenUrl === '') {
            throw new InvalidArgumentException('Authorize URL and token URL must not be empty');
        }

        parent::__construct($clientId, $clientSecret, $tokenUrl, $logger, $httpClient);

        $this->authorizeUrl = $authorizeUrl;
        $this->redirectUri = $redirectUri;
    }

    public function getAuthorizeUrl(): string {
        return $this->authorizeUrl;
    }

    public function getRedirectUri(): ?string {
        return $this->redirectUri;
    }

    /**
     * Token revocation endpoint (RFC 7009); required for revokeToken().
     */
    public function setRevocationUrl(string $revocationUrl): void {
        if ($revocationUrl === '') {
            throw new InvalidArgumentException('Revocation URL must not be empty');
        }
        $this->revocationUrl = $revocationUrl;
    }

    public function getRevocationUrl(): ?string {
        return $this->revocationUrl;
    }

    /**
     * Generate a PKCE code verifier (RFC 7636, 64 chars base64url).
     *
     * The application stores it (like the state) and passes it to both
     * getAuthorizationUrl() and exchangeAuthorizationCode().
     */
    public static function generatePkceVerifier(): string {
        return self::base64UrlEncode(random_bytes(48));
    }

    /**
     * Compute the S256 code challenge for a PKCE verifier (RFC 7636).
     */
    public static function pkceChallenge(string $verifier): string {
        return self::base64UrlEncode(hash('sha256', $verifier, true));
    }

    /**
     * Build the authorization URL the user is redirected to.
     *
     * @param string $state Opaque CSRF token; the application must verify it on callback
     * @param array<int, string> $scopes Requested scopes (provider-specific separator: space)
     * @param array<string, string> $extraParams Additional provider-specific query parameters
     */
    public function getAuthorizationUrl(string $state, array $scopes = [], array $extraParams = [], ?string $pkceVerifier = null): string {
        if ($state === '') {
            throw new InvalidArgumentException('State must not be empty');
        }

        $query = array_merge([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'state' => $state,
        ], $extraParams);

        if ($pkceVerifier !== null) {
            $query['code_challenge'] = self::pkceChallenge($pkceVerifier);
            $query['code_challenge_method'] = 'S256';
        }

        if ($scopes !== []) {
            $query['scope'] = implode(' ', $scopes);
        }

        if ($this->redirectUri !== null) {
            $query['redirect_uri'] = $this->redirectUri;
        }

        $separator = str_contains($this->authorizeUrl, '?') ? '&' : '?';

        return $this->authorizeUrl . $separator . http_build_query($query);
    }

    /**
     * Exchange an authorization code for a token set.
     */
    public function exchangeAuthorizationCode(string $code, ?string $pkceVerifier = null): OAuth2Token {
        if ($code === '') {
            throw new InvalidArgumentException('Authorization code must not be empty');
        }

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
        ];

        if ($this->redirectUri !== null) {
            $params['redirect_uri'] = $this->redirectUri;
        }

        if ($pkceVerifier !== null) {
            $params['code_verifier'] = $pkceVerifier;
        }

        return $this->requestToken($params);
    }

    /**
     * Obtain a fresh token set using a refresh token.
     *
     * Note: providers may omit the refresh token in the response; callers
     * should carry over the previous one (see OAuth2Token::withRefreshToken()).
     */
    public function refreshToken(string $refreshToken): OAuth2Token {
        if ($refreshToken === '') {
            throw new InvalidArgumentException('Refresh token must not be empty');
        }

        return $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Revoke a token at the configured revocation endpoint (RFC 7009).
     *
     * Providers answer 200 even for unknown tokens; HTTP errors surface as
     * the usual typed exceptions.
     *
     * @param string $token Access or refresh token to revoke
     * @param string|null $tokenTypeHint Optional hint ('access_token'/'refresh_token')
     */
    public function revokeToken(string $token, ?string $tokenTypeHint = null): void {
        if ($this->revocationUrl === null) {
            throw new RuntimeException('No revocation URL configured — call setRevocationUrl() first.');
        }
        if ($token === '') {
            throw new InvalidArgumentException('Token must not be empty');
        }

        $params = ['token' => $token];
        if ($tokenTypeHint !== null) {
            $params['token_type_hint'] = $tokenTypeHint;
        }

        $this->post($this->revocationUrl, $this->tokenRequestOptions($params));
    }
}
