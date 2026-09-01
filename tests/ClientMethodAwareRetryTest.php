<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientMethodAwareRetryTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Exceptions\ServiceUnavailableException;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Request, Response};
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Tests\Contracts\Test;

/**
 * Öffentliches request() für beliebige HTTP-/WebDAV-Methoden (Befund C4) und
 * methodenbewusster Retry (Befund B2): Idempotente Verben retryen wie bisher,
 * nicht-idempotente (POST, LOCK, …) nach einem gesendeten Request nur noch
 * per Opt-in, Idempotency-Key oder bei nachweislichem Pre-Send-Fehlschlag.
 */
class ClientMethodAwareRetryTest extends Test {
    private MockHandler $mockHandler;

    /**
     * Der zuletzt gesendete Request. MockHandler::getLastRequest() ist
     * nullable; nach einem abgesetzten Aufruf ist er es nie.
     */
    private function lastRequest(): RequestInterface {
        $request = $this->mockHandler->getLastRequest();
        $this->assertNotNull($request);

        return $request;
    }

    /**
     * @param array<int, \Psr\Http\Message\ResponseInterface|\Throwable|callable> $queue
     */
    private function makeClient(array $queue): ClientAbstract {
        $this->mockHandler = new MockHandler($queue);
        $httpClient = new HttpClient(['handler' => HandlerStack::create($this->mockHandler)]);

        $client = new class('https://dav.example.com', null, false, $httpClient) extends ClientAbstract {};
        $client->setRequestInterval(0.0);
        $client->setBaseRetryDelay(0);

        return $client;
    }

    // ---- C4: beliebige Methoden über request() -------------------------

    public function test_propfind_goes_through_and_retries_on_503_with_retry_after(): void {
        $client = $this->makeClient([
            new Response(503, ['Retry-After' => '0']),
            new Response(207, ['Content-Type' => 'application/xml'], '<multistatus/>'),
        ]);

        $response = $client->request('PROPFIND', '/calendars/user/', ['headers' => ['Depth' => '1']]);

        $this->assertSame(207, $response->getStatusCode());
        $this->assertSame(0, $this->mockHandler->count(), 'PROPFIND muss nach dem 503 wiederholt worden sein.');

        $sent = $this->lastRequest();
        $this->assertSame('PROPFIND', $sent->getMethod());
        $this->assertSame('1', $sent->getHeaderLine('Depth'));
    }

    public function test_method_is_normalized_to_uppercase(): void {
        $client = $this->makeClient([new Response(207, [], '<multistatus/>')]);

        $client->request('propfind', '/dav/');

        $this->assertSame('PROPFIND', $this->lastRequest()->getMethod());
    }

    public function test_empty_method_is_rejected(): void {
        $client = $this->makeClient([]);

        $this->expectException(InvalidArgumentException::class);
        $client->request('  ', '/dav/');
    }

    public function test_mkcol_is_idempotent_and_retried(): void {
        // MKCOL ist laut RFC 4918 idempotent — Retry wie bei GET.
        $client = $this->makeClient([
            new Response(503, ['Retry-After' => '0']),
            new Response(201, [], ''),
        ]);

        $response = $client->request('MKCOL', '/dav/new-folder/');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(0, $this->mockHandler->count());
    }

    public function test_lock_is_not_idempotent_and_not_retried(): void {
        // LOCK fehlt bewusst in IDEMPOTENT_METHODS (RFC 4918: nicht idempotent).
        $client = $this->makeClient([
            new Response(503, ['Retry-After' => '0']),
            new Response(200, [], ''),
        ]);

        try {
            $client->request('LOCK', '/dav/file.txt');
            $this->fail('LOCK hätte den 503 durchreichen müssen.');
        } catch (ServiceUnavailableException $e) {
            $this->assertSame(503, $e->getCode());
        }

        $this->assertSame(1, $this->mockHandler->count(), 'Die zweite Antwort darf nie abgeholt worden sein.');
    }

    // ---- B2: POST wird nach gesendetem Request nicht mehr blind retryt --

    public function test_post_is_not_retried_after_sent_5xx_response(): void {
        $client = $this->makeClient([
            new Response(503, ['Retry-After' => '0']),
            new Response(200, [], '{}'),
        ]);

        try {
            $client->post('/time_entries', ['json' => ['description' => 'work']]);
            $this->fail('POST hätte den 503 durchreichen müssen.');
        } catch (ServiceUnavailableException $e) {
            $this->assertSame(503, $e->getCode());
        }

        // Beweis über die Queue: der zweite (grüne) Response blieb liegen.
        $this->assertSame(1, $this->mockHandler->count(), 'POST darf nach einem gesendeten 5xx nicht wiederholt werden.');
    }

    public function test_post_is_not_retried_on_ambiguous_connect_failure(): void {
        // ConnectException OHNE errno-Kontext (Timeout/abgerissene Verbindung
        // möglich) — nicht unterscheidbar, ob der Request den Server erreichte.
        $client = $this->makeClient([
            new ConnectException('cURL error 28: operation timed out', new Request('POST', '/time_entries')),
            new Response(200, [], '{}'),
        ]);

        try {
            $client->post('/time_entries', ['json' => []]);
            $this->fail('POST hätte die ConnectException durchreichen müssen.');
        } catch (ConnectException $e) {
            $this->assertStringContainsString('timed out', $e->getMessage());
        }

        $this->assertSame(1, $this->mockHandler->count());
    }

    public function test_post_is_retried_on_provable_pre_send_connect_failure(): void {
        // errno 7 (COULDNT_CONNECT): der Request hat den Server nie erreicht —
        // auch ein POST darf dann gefahrlos wiederholt werden.
        $client = $this->makeClient([
            new ConnectException('cURL error 7: connection refused', new Request('POST', '/time_entries')),
            new Response(201, [], '{}'),
        ]);

        $response = $client->post('/time_entries', ['json' => []]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(0, $this->mockHandler->count());
    }

    // ---- B2: Opt-in-Wege für POST-Retries ------------------------------

    public function test_post_with_per_request_opt_in_is_retried(): void {
        $client = $this->makeClient([
            new Response(503, ['Retry-After' => '0']),
            new Response(201, [], '{}'),
        ]);

        $response = $client->post('/time_entries', ['json' => [], 'retry_non_idempotent' => true]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(0, $this->mockHandler->count());
        // Die toolkit-interne Option darf Guzzle nie erreichen.
        $this->assertSame([], $this->lastRequest()->getHeader('retry_non_idempotent'));
    }

    public function test_post_with_client_wide_opt_in_is_retried(): void {
        $client = $this->makeClient([
            new Response(503, ['Retry-After' => '0']),
            new Response(201, [], '{}'),
        ]);
        $client->setRetryNonIdempotent(true);

        $this->assertTrue($client->isRetryNonIdempotentEnabled());

        $response = $client->post('/time_entries', ['json' => []]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(0, $this->mockHandler->count());
    }

    public function test_post_with_idempotency_key_is_retried(): void {
        // Ein Idempotency-Key macht den Request serverseitig deduplizierbar —
        // der Retry ist dann auch für POST wieder erlaubt.
        $client = $this->makeClient([
            new Response(503, ['Retry-After' => '0']),
            new Response(201, [], '{}'),
        ]);

        $response = $client->post('/charges', ['json' => [], 'idempotency_key' => 'key-42']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(0, $this->mockHandler->count());
        $this->assertSame('key-42', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    // ---- B2: idempotente Verben unverändert ----------------------------

    public function test_get_retry_is_unchanged(): void {
        $client = $this->makeClient([
            new Response(503, ['Retry-After' => '0']),
            new Response(200, [], 'ok'),
        ]);

        $response = $client->get('/resource');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
        $this->assertSame(0, $this->mockHandler->count());
    }

    public function test_get_retry_on_ambiguous_connect_failure_is_unchanged(): void {
        $client = $this->makeClient([
            new ConnectException('down', new Request('GET', '/r')),
            new Response(200, [], 'ok'),
        ]);

        $response = $client->get('/r');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $this->mockHandler->count());
    }
}
