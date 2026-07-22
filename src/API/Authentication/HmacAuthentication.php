<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HmacAuthentication.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication;

use APIToolkit\Contracts\Interfaces\API\RequestAwareAuthenticationInterface;
use InvalidArgumentException;

/**
 * Per-request HMAC request signing — a shipped implementation of
 * RequestAwareAuthenticationInterface.
 *
 * For each request a signature is computed over a canonical string made of the
 * HTTP method, the request URI, a timestamp and a hash of the body:
 *
 *   {METHOD}\n{URI}\n{timestamp}\n{sha256(body)}
 *
 * and sent alongside the key id and timestamp in configurable headers. The
 * timestamp lets the server reject replays; the body hash binds the signature
 * to the payload.
 */
class HmacAuthentication implements RequestAwareAuthenticationInterface {
    private string $keyId;
    private string $secret;
    private string $algorithm;
    private string $signatureHeader;
    private string $timestampHeader;
    private string $keyIdHeader;

    /**
     * @param string $keyId Public key/credential id sent with each request
     * @param string $secret Shared secret used for the HMAC (never transmitted)
     * @param string $algorithm HMAC hash algorithm (default sha256)
     * @param string $signatureHeader Header carrying the signature
     * @param string $timestampHeader Header carrying the request timestamp
     * @param string $keyIdHeader Header carrying the key id
     */
    public function __construct(
        string $keyId,
        #[\SensitiveParameter]
        string $secret,
        string $algorithm = 'sha256',
        string $signatureHeader = 'X-Signature',
        string $timestampHeader = 'X-Timestamp',
        string $keyIdHeader = 'X-Key-Id'
    ) {
        if (!in_array($algorithm, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException("Unsupported HMAC algorithm: {$algorithm}");
        }

        $this->keyId = $keyId;
        $this->secret = $secret;
        $this->algorithm = $algorithm;
        $this->signatureHeader = $signatureHeader;
        $this->timestampHeader = $timestampHeader;
        $this->keyIdHeader = $keyIdHeader;
    }

    /**
     * @return array<string, string>
     */
    public function getAuthHeadersFor(string $method, string $uri, ?string $body = null): array {
        return $this->sign($method, $uri, $body, time());
    }

    /**
     * @return array<string, string>
     */
    public function getAuthHeaders(): array {
        // Non-request-aware fallback: sign an empty canonical request. Prefer
        // getAuthHeadersFor(), which ClientAbstract calls automatically.
        return $this->sign('', '', null, time());
    }

    public function getType(): string {
        return 'HMAC';
    }

    public function isValid(): bool {
        return $this->keyId !== '' && $this->secret !== '';
    }

    /**
     * @return array<string, string>
     */
    private function sign(string $method, string $uri, ?string $body, int $timestamp): array {
        $bodyHash = hash('sha256', $body ?? '');
        $canonical = strtoupper($method) . "\n" . $uri . "\n" . $timestamp . "\n" . $bodyHash;
        $signature = hash_hmac($this->algorithm, $canonical, $this->secret);

        return [
            $this->keyIdHeader => $this->keyId,
            $this->timestampHeader => (string) $timestamp,
            $this->signatureHeader => $signature,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array {
        return [
            'keyId' => $this->keyId,
            'secret' => $this->secret === '' ? '' : '[redacted]',
            'algorithm' => $this->algorithm,
        ];
    }
}
