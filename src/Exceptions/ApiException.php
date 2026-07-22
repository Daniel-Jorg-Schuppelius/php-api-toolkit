<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Exceptions;

use ERRORToolkit\Traits\ErrorLog;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\{LogLevel, LoggerInterface};

class ApiException extends Exception {
    use ErrorLog;

    /** Maximum number of response-body characters written to the log context. */
    public const MAX_LOGGED_CONTENT = 2048;

    /**
     * Response header names (lowercase) whose values are redacted before they
     * are written to the log context. Mirrors the request-side redaction in
     * ClientAbstract so a server's Set-Cookie / auth headers do not leak into
     * the logs on a 4xx/5xx (works with any PSR-3 logger, not just the
     * error-toolkit loggers that additionally redact defensively).
     */
    protected const SENSITIVE_RESPONSE_HEADERS = [
        'set-cookie',
        'authorization',
        'proxy-authorization',
        'www-authenticate',
        'cookie',
    ];

    private const REDACTED = '[redacted]';

    protected ?ResponseInterface $response;
    protected ?string $responseContent = null;

    public function __construct(string $message = '', int $code = 0, ?ResponseInterface $response = null, ?Exception $previous = null, ?LoggerInterface $logger = null) {
        parent::__construct($message, $code, $previous);
        $this->initializeLogger($logger);
        $this->response = $response;
        $this->responseContent = $this->extractContent();

        $loggedContent = $this->responseContent;
        if ($loggedContent !== null && mb_strlen($loggedContent) > self::MAX_LOGGED_CONTENT) {
            $loggedContent = mb_substr($loggedContent, 0, self::MAX_LOGGED_CONTENT) . '… [truncated]';
        }

        $context = [
            'status_code' => $code,
            'response_content' => $loggedContent,
        ];

        if ($response !== null) {
            $context['response_headers'] = self::redactHeaders($response->getHeaders());
        }

        // 4xx responses are frequently expected control flow on the caller
        // side (e.g. 404 existence checks) — log those as warning, only
        // 5xx/unknown as error. getContent() still returns the full body.
        $level = ($code >= 400 && $code < 500) ? LogLevel::WARNING : LogLevel::ERROR;

        self::logException($this, $level, $context);
    }

    public function getResponse(): ?ResponseInterface {
        return $this->response;
    }

    public function getContent(): ?string {
        return $this->responseContent;
    }

    /**
     * Parse the response body as an RFC 7807 problem+json document, if it looks
     * like one (contains at least one of type/title/detail/status).
     *
     * @return array<string, mixed>|null
     */
    public function getProblemDetails(): ?array {
        $decoded = $this->decodedBody();

        foreach (['type', 'title', 'detail', 'status'] as $key) {
            if (array_key_exists($key, $decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Best-effort machine-readable error code from common JSON error envelopes
     * (problem+json / {error|code|error_code, …}).
     */
    public function getErrorCode(): ?string {
        $decoded = $this->decodedBody();

        foreach (['error_code', 'code', 'error'] as $key) {
            if (isset($decoded[$key]) && (is_string($decoded[$key]) || is_int($decoded[$key]))) {
                return (string) $decoded[$key];
            }
        }

        return null;
    }

    /**
     * Best-effort human-readable error message from common JSON error envelopes.
     */
    public function getErrorMessage(): ?string {
        $decoded = $this->decodedBody();

        foreach (['detail', 'message', 'error_description'] as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key])) {
                return $decoded[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedBody(): array {
        if ($this->responseContent === null || $this->responseContent === '') {
            return [];
        }

        $decoded = json_decode($this->responseContent, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Redact sensitive response header values before they reach the log
     * context. Non-sensitive headers are kept verbatim.
     *
     * @param array<string, array<int, string>> $headers
     * @return array<string, mixed>
     */
    protected static function redactHeaders(array $headers): array {
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower((string) $name), self::SENSITIVE_RESPONSE_HEADERS, true)) {
                $headers[$name] = self::REDACTED;
            }
        }

        return $headers;
    }

    protected function extractContent(): ?string {
        if ($this->response === null) {
            return null;
        }
        $body = $this->response->getBody();
        $content = $body->getContents();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        return $content;
    }
}
