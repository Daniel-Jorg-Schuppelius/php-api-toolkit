<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiExceptionRedactionTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Exceptions\ApiException;
use GuzzleHttp\Psr7\Response;
use Psr\Log\{AbstractLogger, LoggerInterface};
use Tests\Contracts\Test;

class ApiExceptionRedactionTest extends Test {
    /** @var AbstractLogger&object{records: array<int, array{level: mixed, message: string, context: array<string, mixed>}>} */
    private AbstractLogger $spyLogger;

    private ?LoggerInterface $previousLogger = null;

    protected function setUp(): void {
        parent::setUp();
        $this->previousLogger = ApiException::getLogger();
        $this->spyLogger = new class extends AbstractLogger {
            /** @var array<int, array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }

    protected function tearDown(): void {
        ApiException::setLogger($this->previousLogger);
        parent::tearDown();
    }

    public function test_response_set_cookie_and_auth_headers_are_redacted_in_log(): void {
        $response = new Response(403, [
            'Set-Cookie' => 'SESSIONID=abc123; HttpOnly',
            'WWW-Authenticate' => 'Bearer realm="api"',
            'Content-Type' => 'application/json',
        ], '{"error":"forbidden"}');

        new ForbiddenLikeException('Forbidden', 403, $response, null, $this->spyLogger);

        $context = $this->exceptionContext();
        $this->assertSame('[redacted]', $context['response_headers']['Set-Cookie']);
        $this->assertSame('[redacted]', $context['response_headers']['WWW-Authenticate']);
        $this->assertSame(['application/json'], $context['response_headers']['Content-Type']);

        $serialized = (string) json_encode($this->spyLogger->records);
        $this->assertStringNotContainsString('abc123', $serialized);
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionContext(): array {
        foreach ($this->spyLogger->records as $record) {
            if (array_key_exists('response_headers', $record['context'])) {
                return $record['context'];
            }
        }

        $this->fail('No exception log record with response_headers captured');
    }
}

/** Concrete ApiException so the constructor's auto-logging path is exercised. */
class ForbiddenLikeException extends ApiException {}
