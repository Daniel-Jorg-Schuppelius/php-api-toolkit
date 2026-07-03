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

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Exceptions\ApiException;
use GuzzleHttp\Client as HttpClient;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Provider-neutral OAuth2 Authorization Code grant (RFC 6749 section 4.1).
 *
 * Builds the authorization URL for the browser redirect and exchanges
 * authorization codes / refresh tokens at the token endpoint. State
 * generation, state validation and token persistence remain the
 * responsibility of the consuming application.
 *
 * Extends ClientAbstract, so token endpoint calls share the toolkit's
 * error mapping, throttling and retry behavior (incl. Retry-After).
 */
class OAuth2AuthorizationCodeGrant extends ClientAbstract {
    protected string $clientId;
    protected string $clientSecret;
    protected string $authorizeUrl;
    protected ?string $redirectUri;

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

        parent::__construct($tokenUrl, $logger, false, $httpClient);

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->authorizeUrl = $authorizeUrl;
        $this->redirectUri = $redirectUri;
    }

    public function getClientId(): string {
        return $this->clientId;
    }

    public function getAuthorizeUrl(): string {
        return $this->authorizeUrl;
    }

    public function getRedirectUri(): ?string {
        return $this->redirectUri;
    }

    /**
     * Build the authorization URL the user is redirected to.
     *
     * @param string $state Opaque CSRF token; the application must verify it on callback
     * @param array<int, string> $scopes Requested scopes (provider-specific separator: space)
     * @param array<string, string> $extraParams Additional provider-specific query parameters
     */
    public function getAuthorizationUrl(string $state, array $scopes = [], array $extraParams = []): string {
        if ($state === '') {
            throw new InvalidArgumentException('State must not be empty');
        }

        $query = array_merge([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'state' => $state,
        ], $extraParams);

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
    public function exchangeAuthorizationCode(string $code): OAuth2Token {
        if ($code === '') {
            throw new InvalidArgumentException('Authorization code must not be empty');
        }

        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
        ];

        if ($this->redirectUri !== null) {
            $params['redirect_uri'] = $this->redirectUri;
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
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * @param array<string, string> $params Form parameters for the token endpoint
     */
    protected function requestToken(array $params): OAuth2Token {
        $response = $this->post('', [
            'form_params' => $params,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $payload = json_decode((string) $response->getBody(), true);

        if (!is_array($payload) || !isset($payload['access_token'])) {
            throw new ApiException('OAuth2 token endpoint returned an unexpected payload', $response->getStatusCode(), $response);
        }

        return OAuth2Token::fromResponse($payload);
    }
}
