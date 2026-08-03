<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RateLimit.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;

/**
 * Immutable snapshot of the rate-limit budget advertised by a response.
 *
 * Parses the widespread `X-RateLimit-*` headers (GitHub-style; reset is a Unix
 * timestamp), the IETF `RateLimit-*` draft headers (reset is delta-seconds)
 * and the shapes the AI providers invented on top of them:
 * `x-ratelimit-reset-requests: 6m0s` (Go duration, OpenAI) and
 * `anthropic-ratelimit-requests-reset: 2026-08-03T15:38:00Z` (RFC 3339).
 * Missing fields are null.
 */
class RateLimit {
    public function __construct(
        public readonly ?int $limit,
        public readonly ?int $remaining,
        public readonly ?DateTimeImmutable $resetAt,
    ) {}

    /**
     * Build a RateLimit from a response, or null when no rate-limit headers
     * are present.
     */
    /**
     * Header names per field, standard first, provider dialects after. The
     * request budget wins over the token budget: it is the one that gates the
     * next call at all.
     *
     * @var array<string, list<string>>
     */
    public const HEADERS = [
        'limit' => ['x-ratelimit-limit', 'ratelimit-limit', 'x-ratelimit-limit-requests', 'anthropic-ratelimit-requests-limit'],
        'remaining' => ['x-ratelimit-remaining', 'ratelimit-remaining', 'x-ratelimit-remaining-requests', 'anthropic-ratelimit-requests-remaining'],
        'reset' => ['x-ratelimit-reset', 'ratelimit-reset', 'x-ratelimit-reset-requests', 'x-ratelimit-reset-tokens', 'anthropic-ratelimit-requests-reset', 'anthropic-ratelimit-tokens-reset'],
    ];

    public static function fromResponse(ResponseInterface $response): ?self {
        $limit = self::header($response, self::HEADERS['limit']);
        $remaining = self::header($response, self::HEADERS['remaining']);
        $reset = self::header($response, self::HEADERS['reset']);

        if ($limit === null && $remaining === null && $reset === null) {
            return null;
        }

        return new self(
            is_numeric($limit) ? (int) $limit : null,
            is_numeric($remaining) ? (int) $remaining : null,
            self::parseReset($reset),
        );
    }

    /**
     * Whether the advertised budget is exhausted (remaining is known and 0).
     */
    public function isExhausted(): bool {
        return $this->remaining !== null && $this->remaining <= 0;
    }

    /**
     * Seconds until the window resets (>= 0), or null when unknown.
     */
    public function secondsUntilReset(?int $now = null): ?int {
        if ($this->resetAt === null) {
            return null;
        }

        return max(0, $this->resetAt->getTimestamp() - ($now ?? time()));
    }

    /**
     * @param array<int, string> $names
     */
    private static function header(ResponseInterface $response, array $names): ?string {
        foreach ($names as $name) {
            if ($response->hasHeader($name)) {
                $value = trim($response->getHeaderLine($name));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private static function parseReset(?string $reset): ?DateTimeImmutable {
        if ($reset === null) {
            return null;
        }

        if (ctype_digit($reset)) {
            $value = (int) $reset;
            // A large value is a Unix timestamp (X-RateLimit-Reset); a small
            // value is delta-seconds from now (IETF RateLimit-Reset).
            $timestamp = $value > 1_000_000_000 ? $value : time() + $value;

            return (new DateTimeImmutable)->setTimestamp($timestamp);
        }

        $delay = self::parseDelaySeconds($reset);

        return $delay === null ? null : (new DateTimeImmutable)->setTimestamp(time() + $delay);
    }

    /**
     * Seconds to wait, from the many spellings a wait hint arrives in:
     * delta-seconds (`30`, also fractional `0.5`), Go durations (`1s`,
     * `6m0s`, `1h30m`, OpenAI) and absolute RFC 3339 / HTTP dates (Anthropic).
     * Never negative; null when the value means nothing.
     */
    public static function parseDelaySeconds(string $value, ?int $now = null): ?int {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $now ??= time();

        if (is_numeric($value)) {
            // Fractional seconds are rounded up: waiting a hair too long is
            // free, waiting too little earns the next 429.
            return max(0, (int) ceil((float) $value));
        }

        if (preg_match('/^(?:(\d+(?:\.\d+)?)h)?(?:(\d+(?:\.\d+)?)m)?(?:(\d+(?:\.\d+)?)(?:s|ms))?$/i', $value, $m) === 1
            && ($m[1] ?? '') . ($m[2] ?? '') . ($m[3] ?? '') !== '') {
            $seconds = ((float) ($m[1] ?? 0)) * 3600 + ((float) ($m[2] ?? 0)) * 60 + (float) ($m[3] ?? 0);
            if (str_ends_with(strtolower($value), 'ms')) {
                $seconds = ((float) ($m[3] ?? 0)) / 1000;
            }

            return max(0, (int) ceil($seconds));
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(0, $timestamp - $now);
    }
}
