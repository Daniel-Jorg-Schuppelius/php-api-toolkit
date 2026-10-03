<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2PasswordGrant.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use GuzzleHttp\Client as HttpClient;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * OAuth2 Resource Owner Password Credentials grant (RFC 6749 section 4.3).
 *
 * Used by B2B web services that hand each software installation a client id
 * and authenticate the user with name and password at the token endpoint
 * (e.g. Open Masterdata of the German SHK wholesale). The client secret is
 * optional: many of these providers register public clients and use the
 * client id as a shared secret only.
 */
class OAuth2PasswordGrant extends OAuth2GrantAbstract {
    public function __construct(
        string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        string $tokenUrl,
        ?LoggerInterface $logger = null,
        ?HttpClient $httpClient = null
    ) {
        parent::__construct($clientId, $clientSecret, $tokenUrl, $logger, $httpClient);

        $this->allowEmptyClientSecret = true;
    }

    /**
     * Request a token with the resource owner's credentials (grant_type=password).
     *
     * @param array<int, string> $scopes Requested scopes (space-joined)
     * @param array<string, string> $extraParams Provider-specific form parameters; they are
     *                                           merged last, so a provider that labels its
     *                                           password flow differently can override grant_type
     */
    public function fetchToken(string $username, #[\SensitiveParameter] string $password, array $scopes = [], array $extraParams = []): OAuth2Token {
        if ($username === '') {
            throw new InvalidArgumentException('Username must not be empty');
        }

        $params = [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ];

        if ($scopes !== []) {
            $params['scope'] = implode(' ', $scopes);
        }

        return $this->requestToken(array_merge($params, $extraParams));
    }

    /**
     * Exchange a refresh token for a new access token (grant_type=refresh_token).
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
}
