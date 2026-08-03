<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TooManyRequestsException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Exceptions;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * HTTP 429 — two very different situations share one status code:
 *
 *  - a temporary rate limit: waiting (or a higher request interval) helps,
 *  - an exhausted quota / inactive billing: waiting never helps, the account
 *    is out of budget.
 *
 * Both the default message and {@see isQuotaExhausted()} tell them apart so
 * callers stop hunting for a throttle setting when the plan is the problem.
 */
class TooManyRequestsException extends ApiException {
    /**
     * Error codes that mean "out of budget" rather than "too fast". Used
     * across OpenAI-compatible APIs (OpenAI, Azure OpenAI, OpenRouter, Groq)
     * and mirrored by several other providers.
     *
     * @var list<string>
     */
    public const QUOTA_ERROR_CODES = [
        'insufficient_quota',
        'quota_exceeded',
        'billing_hard_limit_reached',
        'billing_not_active',
        'account_deactivated',
    ];

    public function __construct(string $message = '', int $code = 429, ?ResponseInterface $response = null, ?Exception $previous = null, ?LoggerInterface $logger = null) {
        parent::__construct($message !== '' ? $message : self::describe($response), $code, $response, $previous, $logger);
    }

    /** Is the budget spent rather than the rate window? Then a retry is futile. */
    public function isQuotaExhausted(): bool {
        return self::hasQuotaCode($this->getErrorCodes());
    }

    /** Same check before an exception exists (429 mapping, retry policy). */
    public static function isQuotaResponse(?ResponseInterface $response): bool {
        return self::hasQuotaCode(self::errorCodesOf($response));
    }

    /** @param list<string> $codes */
    private static function hasQuotaCode(array $codes): bool {
        return array_intersect(array_map('strtolower', $codes), self::QUOTA_ERROR_CODES) !== [];
    }

    /**
     * Default message. Naming only the rate-limit cause sent callers looking
     * for a throttle setting while their account was simply out of budget.
     */
    private static function describe(?ResponseInterface $response): string {
        if (self::isQuotaResponse($response)) {
            return 'Too Many Requests: account quota exhausted — check plan and billing, retrying will not help';
        }

        $retryAfter = trim($response?->getHeaderLine('Retry-After') ?? '');
        if ($retryAfter !== '' && ctype_digit($retryAfter)) {
            return sprintf('Too Many Requests: rate limited, server asks to retry after %ds', (int) $retryAfter);
        }

        return 'Too Many Requests: rate limited — consider a higher value for Client->requestInterval';
    }
}
