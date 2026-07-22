<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2DeviceCodeGrant.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use APIToolkit\Exceptions\{ApiException, BadRequestException};
use GuzzleHttp\Client as HttpClient;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Provider-neutral OAuth2 Device Authorization Grant (RFC 8628).
 *
 * The standard flow for headless/CLI tools and input-constrained devices:
 * request a device + user code, show the user a verification URL, then poll
 * the token endpoint until the user approves (or the request expires).
 *
 * Token endpoint mechanics (client authentication, error mapping, throttling,
 * retries) are shared via OAuth2GrantAbstract.
 */
class OAuth2DeviceCodeGrant extends OAuth2GrantAbstract {
    protected string $deviceAuthorizationUrl;

    /**
     * @param string $clientId OAuth2 client id
     * @param string $clientSecret Client secret (may be empty for public device clients)
     * @param string $deviceAuthorizationUrl Full device authorization endpoint URL
     * @param string $tokenUrl Full token endpoint URL
     * @param LoggerInterface|null $logger PSR-3 logger instance
     * @param HttpClient|null $httpClient Optional pre-configured Guzzle client (e.g. MockHandler in tests)
     */
    public function __construct(
        string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        string $deviceAuthorizationUrl,
        string $tokenUrl,
        ?LoggerInterface $logger = null,
        ?HttpClient $httpClient = null
    ) {
        if ($deviceAuthorizationUrl === '') {
            throw new InvalidArgumentException('Device authorization URL must not be empty');
        }

        parent::__construct($clientId, $clientSecret, $tokenUrl, $logger, $httpClient);

        // Device clients are frequently public (no secret).
        $this->allowEmptyClientSecret = true;
        $this->deviceAuthorizationUrl = $deviceAuthorizationUrl;
    }

    /**
     * Request a device + user code (RFC 8628 section 3.1/3.2).
     *
     * @param array<int, string> $scopes Requested scopes
     * @return array{device_code: string, user_code: string, verification_uri: string, verification_uri_complete?: string, expires_in: int, interval: int} Decoded device authorization response
     */
    public function requestDeviceCode(array $scopes = []): array {
        $params = ['client_id' => $this->clientId];
        if ($scopes !== []) {
            $params['scope'] = implode(' ', $scopes);
        }

        $response = $this->post($this->deviceAuthorizationUrl, [
            'form_params' => $params,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload) || !isset($payload['device_code'], $payload['user_code'])) {
            throw new ApiException('Device authorization endpoint returned an unexpected payload', $response->getStatusCode(), $response);
        }

        // Normalize the poll interval (RFC 8628 default is 5 seconds).
        $payload['interval'] = isset($payload['interval']) && is_numeric($payload['interval']) ? (int) $payload['interval'] : 5;
        $payload['expires_in'] = isset($payload['expires_in']) && is_numeric($payload['expires_in']) ? (int) $payload['expires_in'] : 0;

        return $payload;
    }

    /**
     * Exchange an approved device code for a token — a single token request.
     *
     * @throws BadRequestException while the authorization is still pending
     *                             (error=authorization_pending / slow_down)
     */
    public function exchangeDeviceCode(string $deviceCode): OAuth2Token {
        if ($deviceCode === '') {
            throw new InvalidArgumentException('Device code must not be empty');
        }

        return $this->requestToken([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'device_code' => $deviceCode,
        ]);
    }

    /**
     * Poll the token endpoint until the user approves the device (or it times
     * out). Honours the server's authorization_pending / slow_down responses.
     *
     * @param string $deviceCode Device code from requestDeviceCode()
     * @param int $interval Poll interval in seconds (RFC 8628 minimum is 5)
     * @param int|null $expiresIn Optional overall timeout in seconds
     */
    public function pollForToken(string $deviceCode, int $interval = 5, ?int $expiresIn = null): OAuth2Token {
        $interval = max(1, $interval);
        $deadline = $expiresIn !== null ? time() + $expiresIn : null;

        while (true) {
            try {
                return $this->exchangeDeviceCode($deviceCode);
            } catch (BadRequestException $e) {
                $error = $e->getErrorCode();
                if ($error === 'slow_down') {
                    $interval += 5;
                } elseif ($error !== 'authorization_pending') {
                    // access_denied, expired_token, invalid_grant, …
                    throw $e;
                }
            }

            if ($deadline !== null && time() >= $deadline) {
                throw new RuntimeException('Device authorization timed out before the user approved the request');
            }

            $this->wait($interval);
        }
    }

    /**
     * Sleep between poll attempts. Overridable so tests can poll without delay.
     */
    protected function wait(int $seconds): void {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
