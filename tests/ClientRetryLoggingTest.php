<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientRetryLoggingTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Exceptions\{ApiException, InternalServerErrorException, NotFoundException, ServiceUnavailableException};
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Log\{AbstractLogger, LogLevel, LoggerInterface};
use Tests\Contracts\Test;

/**
 * Ein Versuch, der wiederholt wird, ist nur eine Warnung; erst der
 * endgültige Fehlschlag wird als Fehler protokolliert — und genau einmal.
 * Vorher schrieb jede 5xx-Antwort schon beim Erzeugen der Exception ein
 * ERROR, auch wenn der nächste Versuch klappte.
 */
class ClientRetryLoggingTest extends Test {
    private ?LoggerInterface $previousLogger = null;

    private ?LoggerInterface $previousExceptionLogger = null;

    /** @var AbstractLogger&object{records: array<int, array{level: mixed, message: string}>} */
    private AbstractLogger $spyLogger;

    protected function setUp(): void {
        parent::setUp();
        $this->previousLogger = ClientAbstract::getLogger();
        $this->previousExceptionLogger = ApiException::getLogger();
        $this->spyLogger = new class extends AbstractLogger {
            /** @var array<int, array{level: mixed, message: string}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void {
                $this->records[] = ['level' => $level, 'message' => (string) $message];
            }
        };
        // Der Trait-Logger ist je Klasse statisch: frühere Tests können ApiException fest verdrahtet haben.
        ApiException::setLogger($this->spyLogger);
    }

    protected function tearDown(): void {
        ClientAbstract::setLogger($this->previousLogger);
        ApiException::setLogger($this->previousExceptionLogger);
        parent::tearDown();
    }

    /** @param array<int, Response> $queue */
    private function client(array $queue, int $maxRetries = 3): ClientAbstract {
        $http = new HttpClient(['handler' => HandlerStack::create(new MockHandler($queue))]);
        $client = new class('https://api.example.com', $this->spyLogger, false, $http) extends ClientAbstract {};
        $client->setRequestInterval(0.0);
        $client->setBaseRetryDelay(0);
        $client->setMaxRetries($maxRetries);

        return $client;
    }

    /** @return list<string> */
    private function levels(string $level): array {
        return array_values(array_map(
            static fn (array $r): string => $r['message'],
            array_filter($this->spyLogger->records, static fn (array $r): bool => $r['level'] === $level),
        ));
    }

    public function test_retried_attempt_is_only_a_warning(): void {
        $this->client([new Response(503, ['Retry-After' => '0']), new Response(200, [], '{}')])->get('/items');

        $this->assertSame([], $this->levels(LogLevel::ERROR));
        $this->assertCount(1, array_filter($this->levels(LogLevel::WARNING), static fn (string $m): bool => str_starts_with($m, 'Retrying ')));
    }

    public function test_final_failure_is_logged_once_as_error(): void {
        try {
            $this->client([new Response(503, ['Retry-After' => '0']), new Response(503, ['Retry-After' => '0'])], 2)->get('/items');
            $this->fail('ServiceUnavailableException erwartet');
        } catch (ServiceUnavailableException) {
        }

        $errors = $this->levels(LogLevel::ERROR);
        $this->assertCount(1, $errors, implode("\n", $errors));
        $this->assertStringContainsString(ServiceUnavailableException::class, $errors[0]);
    }

    public function test_non_retryable_responses_are_logged_immediately_with_their_level(): void {
        try {
            $this->client([new Response(404)])->get('/missing');
        } catch (NotFoundException) {
        }
        $this->assertSame([], $this->levels(LogLevel::ERROR));
        $this->assertCount(1, array_filter($this->levels(LogLevel::WARNING), static fn (string $m): bool => str_contains($m, NotFoundException::class)));

        try {
            $this->client([new Response(500)])->get('/broken');
        } catch (InternalServerErrorException) {
        }
        $this->assertCount(1, $this->levels(LogLevel::ERROR));
    }
}
