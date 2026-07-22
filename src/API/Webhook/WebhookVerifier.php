<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookVerifier.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Webhook;

use InvalidArgumentException;

/**
 * Verifies inbound webhook signatures (HMAC) in constant time.
 *
 * Two common shapes are supported:
 *  - a bare HMAC of the raw request body (verify()), accepting hex or base64
 *    signatures, optionally prefixed with the algorithm (e.g. "sha256=…");
 *  - a Stripe-style "t=<timestamp>,v1=<sig>" header where the signature is the
 *    HMAC of "<timestamp>.<body>" and a timestamp tolerance guards against
 *    replay (verifyTimestamped()).
 *
 * The comparison always uses hash_equals() so it does not leak via timing.
 */
class WebhookVerifier {
    private string $algorithm;

    public function __construct(string $algorithm = 'sha256') {
        if (!in_array($algorithm, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException("Unsupported HMAC algorithm: {$algorithm}");
        }
        $this->algorithm = $algorithm;
    }

    /**
     * Verify a signature computed as HMAC(secret, rawBody).
     *
     * Accepts the signature as lowercase hex or base64, with or without an
     * "algo=" prefix (as some providers send, e.g. "sha256=abcdef…").
     */
    public function verify(string $rawBody, string $signature, #[\SensitiveParameter] string $secret): bool {
        if ($signature === '' || $secret === '') {
            return false;
        }

        $signature = self::stripAlgorithmPrefix($signature);

        $expectedHex = hash_hmac($this->algorithm, $rawBody, $secret);
        if (hash_equals($expectedHex, strtolower($signature))) {
            return true;
        }

        $expectedBase64 = base64_encode((string) hash_hmac($this->algorithm, $rawBody, $secret, true));

        return hash_equals($expectedBase64, $signature);
    }

    /**
     * Verify a Stripe-style timestamped signature header
     * ("t=<unix-ts>,v1=<hex-sig>[,v1=<hex-sig>…]").
     *
     * The signed payload is "<timestamp>.<rawBody>". Signatures older/newer
     * than $tolerance seconds are rejected to prevent replay ($tolerance <= 0
     * disables the check).
     *
     * @param int|null $now Reference time (defaults to time(); injectable for tests)
     */
    public function verifyTimestamped(string $rawBody, string $signatureHeader, #[\SensitiveParameter] string $secret, int $tolerance = 300, ?int $now = null): bool {
        if ($signatureHeader === '' || $secret === '') {
            return false;
        }

        $parsed = self::parseSignatureHeader($signatureHeader);
        if ($parsed['t'] === [] || $parsed['signatures'] === []) {
            return false;
        }

        $timestamp = $parsed['t'][0];
        if (!ctype_digit($timestamp)) {
            return false;
        }

        $now ??= time();
        if ($tolerance > 0 && abs($now - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac($this->algorithm, $timestamp . '.' . $rawBody, $secret);

        $valid = false;
        // Compare against every provided signature without early-out so the
        // work (and timing) does not depend on which one matches.
        foreach ($parsed['signatures'] as $candidate) {
            if (hash_equals($expected, strtolower($candidate))) {
                $valid = true;
            }
        }

        return $valid;
    }

    private static function stripAlgorithmPrefix(string $signature): string {
        $pos = strpos($signature, '=');
        if ($pos !== false && preg_match('/^[a-z0-9+\-]+$/i', substr($signature, 0, $pos)) === 1
            && strlen($signature) - $pos > 8) {
            // Only strip a short leading "algo=" token, never a base64 '=' pad.
            $prefix = substr($signature, 0, $pos);
            if (strlen($prefix) <= 12) {
                return substr($signature, $pos + 1);
            }
        }

        return $signature;
    }

    /**
     * @return array{t: array<int, string>, signatures: array<int, string>}
     */
    private static function parseSignatureHeader(string $header): array {
        $result = ['t' => [], 'signatures' => []];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$key, $value] = $pair;
            $key = trim($key);
            if ($key === 't') {
                $result['t'][] = trim($value);
            } elseif ($key === 'v1') {
                $result['signatures'][] = trim($value);
            }
        }

        return $result;
    }
}
