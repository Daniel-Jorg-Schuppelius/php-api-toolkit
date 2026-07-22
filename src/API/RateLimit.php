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
 * Parses both the widespread `X-RateLimit-*` headers (GitHub-style; reset is a
 * Unix timestamp) and the IETF `RateLimit-*` draft headers (reset is
 * delta-seconds). Missing fields are null.
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
    public static function fromResponse(ResponseInterface $response): ?self {
        $limit = self::header($response, ['x-ratelimit-limit', 'ratelimit-limit']);
        $remaining = self::header($response, ['x-ratelimit-remaining', 'ratelimit-remaining']);
        $reset = self::header($response, ['x-ratelimit-reset', 'ratelimit-reset']);

        if ($limit === null && $remaining === null && $reset === null) {
            return null;
        }

        return new self(
            $limit !== null ? (int) $limit : null,
            $remaining !== null ? (int) $remaining : null,
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
        if ($reset === null || !ctype_digit($reset)) {
            return null;
        }

        $value = (int) $reset;
        // A large value is a Unix timestamp (X-RateLimit-Reset); a small value
        // is delta-seconds from now (IETF RateLimit-Reset).
        $timestamp = $value > 1_000_000_000 ? $value : time() + $value;

        return (new DateTimeImmutable)->setTimestamp($timestamp);
    }
}
