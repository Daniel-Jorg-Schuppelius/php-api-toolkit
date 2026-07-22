<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2GrantAbstract.php
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
use RuntimeException;

/**
 * Shared token endpoint mechanics for OAuth2 grants (RFC 6749).
 *
 * Holds the client credentials, performs the token endpoint POST with the
 * configured client authentication method and maps the response to an
 * OAuth2Token. Concrete grants (authorization code, client credentials)
 * only assemble their grant-specific form parameters.
 *
 * Extends ClientAbstract, so token endpoint calls share the toolkit's
 * error mapping, throttling and retry behavior (incl. Retry-After).
 */
abstract class OAuth2GrantAbstract extends ClientAbstract {
    /** Client credentials in the request body (RFC 6749 default of the toolkit, e.g. FedEx). */
    public const AUTH_METHOD_POST = 'client_secret_post';

    /** Client credentials as HTTP Basic Authorization header (RFC 6749 section 2.3.1 default, e.g. UPS). */
    public const AUTH_METHOD_BASIC = 'client_secret_basic';

    /** Signed JWT client assertion (RFC 7523, e.g. Microsoft Entra ID certificate credentials). */
    public const AUTH_METHOD_PRIVATE_KEY_JWT = 'private_key_jwt';

    protected string $clientId;
    protected string $clientSecret;
    protected string $tokenAuthMethod = self::AUTH_METHOD_POST;

    protected ?string $assertionPrivateKey = null;
    protected ?string $assertionPassphrase = null;
    protected ?string $assertionCertificate = null;
    protected int $assertionLifetime = 300;

    /**
     * Whether an empty client secret is acceptable for client_secret_post
     * (public/PKCE clients). Confidential grants (client credentials) keep this
     * false so a missing secret is rejected; the authorization-code grant sets
     * it true.
     */
    protected bool $allowEmptyClientSecret = false;

    /**
     * @param string $clientId OAuth2 client id
     * @param string $clientSecret OAuth2 client secret; may be empty only when
     *                             the grant authenticates via setPrivateKeyJwt()
     * @param string $tokenUrl Full token endpoint URL
     * @param LoggerInterface|null $logger PSR-3 logger instance
     * @param HttpClient|null $httpClient Optional pre-configured Guzzle client (e.g. MockHandler in tests)
     */
    public function __construct(
        string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        string $tokenUrl,
        ?LoggerInterface $logger = null,
        ?HttpClient $httpClient = null
    ) {
        if ($clientId === '') {
            throw new InvalidArgumentException('Client id must not be empty');
        }
        if ($tokenUrl === '') {
            throw new InvalidArgumentException('Token URL must not be empty');
        }

        parent::__construct($tokenUrl, $logger, false, $httpClient);

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function getClientId(): string {
        return $this->clientId;
    }

    /**
     * Keep credentials out of var_dump()/print_r()/DI-container dumps and
     * crash reporters. #[\SensitiveParameter] already masks them in stack
     * traces; this covers the reflection/serialization dump paths.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret === '' ? '' : '[redacted]',
            'tokenAuthMethod' => $this->tokenAuthMethod,
            'assertionPrivateKey' => $this->assertionPrivateKey === null ? null : '[redacted]',
            'assertionPassphrase' => $this->assertionPassphrase === null ? null : '[redacted]',
            'assertionCertificate' => $this->assertionCertificate,
            'assertionLifetime' => $this->assertionLifetime,
            'baseUrl' => $this->baseUrl,
        ];
    }

    /**
     * How client credentials are sent to the token/revocation endpoint:
     * AUTH_METHOD_POST (body, default), AUTH_METHOD_BASIC (Authorization
     * header) or AUTH_METHOD_PRIVATE_KEY_JWT (signed assertion; configured
     * implicitly by setPrivateKeyJwt()).
     */
    public function setTokenAuthMethod(string $method): void {
        if (!in_array($method, [self::AUTH_METHOD_POST, self::AUTH_METHOD_BASIC, self::AUTH_METHOD_PRIVATE_KEY_JWT], true)) {
            throw new InvalidArgumentException("Unknown token auth method: {$method}");
        }
        $this->tokenAuthMethod = $method;
    }

    public function getTokenAuthMethod(): string {
        return $this->tokenAuthMethod;
    }

    /**
     * Authenticate token endpoint calls with a signed JWT client assertion
     * (private_key_jwt, RFC 7523 section 2.2) instead of a client secret.
     *
     * Requires the openssl extension. The assertion is signed with RS256;
     * when a certificate is provided, its x5t/x5t#S256 thumbprints are
     * included in the JWT header (required by Microsoft Entra ID
     * certificate credentials).
     *
     * @param string $privateKeyPem PEM-encoded private key
     * @param string|null $certificatePem PEM-encoded X.509 certificate for the x5t/x5t#S256 header
     * @param string|null $passphrase Passphrase of the private key, if any
     * @param int $assertionLifetime Assertion validity in seconds (default 300)
     */
    public function setPrivateKeyJwt(#[\SensitiveParameter] string $privateKeyPem, ?string $certificatePem = null, #[\SensitiveParameter] ?string $passphrase = null, int $assertionLifetime = 300): void {
        if ($privateKeyPem === '') {
            throw new InvalidArgumentException('Private key must not be empty');
        }
        if ($assertionLifetime < 1) {
            throw new InvalidArgumentException('Assertion lifetime must be at least 1 second');
        }

        $this->assertionPrivateKey = $privateKeyPem;
        $this->assertionCertificate = $certificatePem;
        $this->assertionPassphrase = $passphrase;
        $this->assertionLifetime = $assertionLifetime;
        $this->tokenAuthMethod = self::AUTH_METHOD_PRIVATE_KEY_JWT;
    }

    protected static function base64UrlEncode(string $binary): string {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * @param array<string, string> $params Form parameters for the token endpoint
     */
    protected function requestToken(array $params): OAuth2Token {
        $response = $this->post('', $this->tokenRequestOptions($params));

        $payload = json_decode((string) $response->getBody(), true);

        if (!is_array($payload) || !isset($payload['access_token'])) {
            throw new ApiException('OAuth2 token endpoint returned an unexpected payload', $response->getStatusCode(), $response);
        }

        return OAuth2Token::fromResponse($payload);
    }

    /**
     * Build the request options (form params + client authentication) for
     * token and revocation endpoint calls.
     *
     * @param array<string, string> $params Form parameters without client credentials
     * @return array<string, mixed>
     */
    protected function tokenRequestOptions(array $params): array {
        $headers = ['Accept' => 'application/json'];

        if ($this->tokenAuthMethod === self::AUTH_METHOD_BASIC) {
            // RFC 6749 section 2.3.1: credentials in the header, not the body.
            $headers['Authorization'] = 'Basic ' . base64_encode("{$this->clientId}:{$this->requireClientSecret()}");
        } elseif ($this->tokenAuthMethod === self::AUTH_METHOD_PRIVATE_KEY_JWT) {
            $params['client_id'] = $this->clientId;
            $params['client_assertion_type'] = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
            $params['client_assertion'] = $this->buildClientAssertion();
        } else {
            // client_secret_post. A public (PKCE) client has no secret and
            // sends only client_id; a confidential client must provide one.
            $params['client_id'] = $this->clientId;
            if ($this->clientSecret !== '') {
                $params['client_secret'] = $this->clientSecret;
            } elseif (!$this->allowEmptyClientSecret) {
                $params['client_secret'] = $this->requireClientSecret();
            }
        }

        return [
            'form_params' => $params,
            'headers' => $headers,
        ];
    }

    protected function requireClientSecret(): string {
        if ($this->clientSecret === '') {
            throw new RuntimeException('Client secret is required for client_secret_post/client_secret_basic — use setPrivateKeyJwt() for assertion-based clients.');
        }

        return $this->clientSecret;
    }

    /**
     * Build the signed JWT client assertion (RS256) for private_key_jwt.
     *
     * The audience is the token endpoint URL; iss/sub are the client id
     * (RFC 7523 section 3).
     */
    protected function buildClientAssertion(): string {
        if ($this->assertionPrivateKey === null) {
            throw new RuntimeException('No private key configured — call setPrivateKeyJwt() first.');
        }
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('private_key_jwt requires the openssl extension');
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        if ($this->assertionCertificate !== null) {
            $sha1 = openssl_x509_fingerprint($this->assertionCertificate, 'sha1', true);
            $sha256 = openssl_x509_fingerprint($this->assertionCertificate, 'sha256', true);
            if ($sha1 === false || $sha256 === false) {
                self::logErrorAndThrow(RuntimeException::class, 'Could not compute the thumbprint of the configured certificate');
            }
            $header['x5t'] = self::base64UrlEncode($sha1);
            $header['x5t#S256'] = self::base64UrlEncode($sha256);
        }

        $now = time();
        $claims = [
            'iss' => $this->clientId,
            'sub' => $this->clientId,
            'aud' => $this->getBaseUrl(),
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->assertionLifetime,
        ];

        $signingInput = self::base64UrlEncode((string) json_encode($header)) . '.' . self::base64UrlEncode((string) json_encode($claims));

        $key = openssl_pkey_get_private($this->assertionPrivateKey, $this->assertionPassphrase);
        if ($key === false) {
            self::logErrorAndThrow(RuntimeException::class, 'Could not load the configured private key');
        }

        if (!openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            self::logErrorAndThrow(RuntimeException::class, 'Signing the client assertion failed');
        }

        return $signingInput . '.' . self::base64UrlEncode($signature);
    }
}
