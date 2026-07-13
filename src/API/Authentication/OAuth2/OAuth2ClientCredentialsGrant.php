<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2ClientCredentialsGrant.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

/**
 * Provider-neutral OAuth2 Client Credentials grant (RFC 6749 section 4.4).
 *
 * Machine-to-machine flow without user interaction: the client authenticates
 * with its own credentials at the token endpoint and receives a short-lived
 * access token. There is no refresh token in this grant — expired tokens are
 * simply fetched again (see OAuth2ClientCredentialsAuthentication for the
 * automatic variant with pluggable token storage).
 *
 * Client authentication supports the form body (default, e.g. FedEx), an
 * HTTP Basic header (setTokenAuthMethod(AUTH_METHOD_BASIC), e.g. UPS) and
 * signed JWT assertions (setPrivateKeyJwt(), e.g. Microsoft Entra ID
 * certificate credentials) — see OAuth2GrantAbstract.
 */
class OAuth2ClientCredentialsGrant extends OAuth2GrantAbstract {
    /**
     * Request a fresh access token (grant_type=client_credentials).
     *
     * @param array<int, string> $scopes Requested scopes (space-joined, e.g. ['https://graph.microsoft.com/.default'])
     * @param array<string, string> $extraParams Additional provider-specific form parameters (e.g. ['audience' => '...'])
     */
    public function fetchToken(array $scopes = [], array $extraParams = []): OAuth2Token {
        $params = $extraParams;
        $params['grant_type'] = 'client_credentials';

        if ($scopes !== []) {
            $params['scope'] = implode(' ', $scopes);
        }

        return $this->requestToken($params);
    }
}
