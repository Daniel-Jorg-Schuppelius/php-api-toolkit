<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientQuotaAndAiDialectsTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\API\RateLimit;
use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Exceptions\{ApiException, TooManyRequestsException};
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Tests\Contracts\Test;

/**
 * Quota-vs-Rate-Limit-Unterscheidung und die Header-/Fehlerkörper-Dialekte
 * der KI-Anbieter (OpenAI-Hülle, Go-Dauern, RFC-3339-Resets).
 */
class ClientQuotaAndAiDialectsTest extends Test {
    /** Echter OpenAI-Körper bei aufgebrauchtem Kontingent (429). */
    private const OPENAI_QUOTA_BODY = '{"error":{"message":"You exceeded your current quota, please check your plan and billing details.","type":"insufficient_quota","param":null,"code":"insufficient_quota"}}';

    private function makeClient(MockHandler $mock): QuotaDialectTestClient {
        $handler = HandlerStack::create($mock);
        $client = new QuotaDialectTestClient('https://api.example.com', null, false, new HttpClient(['handler' => $handler]));
        $client->setRequestInterval(0.0);
        $client->setBaseRetryDelay(0);
        $client->setMaxRetryDelay(0);

        return $client;
    }

    // ---- TooManyRequestsException -------------------------------------

    public function test_quota_429_names_the_real_cause(): void {
        $e = new TooManyRequestsException('', 429, new Response(429, [], self::OPENAI_QUOTA_BODY));

        $this->assertTrue($e->isQuotaExhausted());
        $this->assertStringContainsString('quota exhausted', $e->getMessage());
        $this->assertStringNotContainsString('requestInterval', $e->getMessage());
    }

    public function test_rate_limit_429_with_retry_after_names_the_wait(): void {
        $e = new TooManyRequestsException('', 429, new Response(429, ['Retry-After' => '17']));

        $this->assertFalse($e->isQuotaExhausted());
        $this->assertStringContainsString('retry after 17s', $e->getMessage());
    }

    public function test_plain_rate_limit_429_still_points_at_request_interval(): void {
        $e = new TooManyRequestsException('', 429, new Response(429));

        $this->assertFalse($e->isQuotaExhausted());
        $this->assertStringContainsString('requestInterval', $e->getMessage());
    }

    public function test_explicit_message_is_kept(): void {
        $e = new TooManyRequestsException('custom', 429, new Response(429, [], self::OPENAI_QUOTA_BODY));

        $this->assertSame('custom', $e->getMessage());
        $this->assertTrue($e->isQuotaExhausted()); // classification independent of message
    }

    // ---- ApiException error envelopes ---------------------------------

    public function test_error_codes_include_nested_openai_envelope(): void {
        $e = new ApiException('x', 429, new Response(429, [], self::OPENAI_QUOTA_BODY));

        $this->assertSame(['insufficient_quota'], $e->getErrorCodes());
        $this->assertSame('insufficient_quota', $e->getErrorCode());
        $this->assertStringContainsString('exceeded your current quota', (string) $e->getErrorMessage());
    }

    public function test_error_codes_collect_type_and_code_when_they_differ(): void {
        $body = '{"error":{"message":"nope","type":"invalid_request_error","code":"model_not_found"}}';
        $e = new ApiException('x', 404, new Response(404, [], $body));

        $this->assertSame(['model_not_found', 'invalid_request_error'], $e->getErrorCodes());
    }

    public function test_flat_envelopes_keep_working(): void {
        $e = new ApiException('x', 400, new Response(400, [], '{"error":"bad_field","message":"Feld kaputt"}'));

        $this->assertSame(['bad_field'], $e->getErrorCodes());
        $this->assertSame('Feld kaputt', $e->getErrorMessage());
    }

    public function test_error_codes_of_reads_a_response_without_consuming_it(): void {
        $response = new Response(429, [], self::OPENAI_QUOTA_BODY);

        $this->assertSame(['insufficient_quota'], ApiException::errorCodesOf($response));
        // Body bleibt für den nächsten Leser lesbar (rewinded).
        $this->assertSame(self::OPENAI_QUOTA_BODY, (string) $response->getBody()->getContents());
    }

    // ---- Retry policy --------------------------------------------------

    public function test_quota_429_is_not_retried(): void {
        $mock = new MockHandler([
            new Response(429, [], self::OPENAI_QUOTA_BODY),
            new Response(200, [], '{}'),
        ]);
        $client = $this->makeClient($mock);

        try {
            $client->get('/v1/responses');
            $this->fail('Quota-429 wurde nicht geworfen.');
        } catch (TooManyRequestsException $e) {
            $this->assertTrue($e->isQuotaExhausted());
        }

        // Genau EIN Versuch — die zweite (grüne) Antwort wurde nie abgeholt.
        $this->assertSame(1, count($mock) === 0 ? 2 : 2 - count($mock));
    }

    public function test_rate_limit_429_is_still_retried(): void {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '0']),
            new Response(200, [], '{"ok":true}'),
        ]);
        $client = $this->makeClient($mock);

        $response = $client->get('/v1/responses');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, count($mock));
    }

    // ---- Endpoint-style base URLs --------------------------------------

    public function test_build_url_caps_duplicated_endpoint_segments(): void {
        $client = new QuotaDialectTestClient('https://api.openai.com/v1/responses');

        // Geschwister-Endpunkt: dupliziertes /v1 wird gekappt, nicht angehängt.
        $this->assertSame('https://api.openai.com/v1/models', $client->buildUrl('/v1/models'));
        // Identischer Endpunkt bleibt stabil.
        $this->assertSame('https://api.openai.com/v1/responses', $client->buildUrl('/v1/responses'));
        // Leerer Pfad = die Basis selbst.
        $this->assertSame('https://api.openai.com/v1/responses', $client->buildUrl(''));
    }

    public function test_build_url_keeps_gateway_prefixes(): void {
        $client = new QuotaDialectTestClient('https://gw.example.com/proxy/v1');

        // Letztes Vorkommen gewinnt: /proxy bleibt, nur das doppelte /v1 fällt.
        $this->assertSame('https://gw.example.com/proxy/v1/models', $client->buildUrl('/v1/models'));
    }

    public function test_build_url_plain_host_base_appends(): void {
        $client = new QuotaDialectTestClient('https://api.example.com');

        $this->assertSame('https://api.example.com/v2/items', $client->buildUrl('/v2/items'));
        $this->assertSame('https://api.example.com/v2/items?limit=5', $client->buildUrl('v2/items?limit=5'));
    }

    // ---- Retry-After & Reset dialects ----------------------------------

    public function test_retry_after_go_duration_is_parsed(): void {
        $client = $this->makeClient(new MockHandler);

        $this->assertSame(360, $client->exposeRetryAfterSeconds(new Response(429, ['Retry-After' => '6m0s'])));
        $this->assertSame(1, $client->exposeRetryAfterSeconds(new Response(429, ['Retry-After' => '0.5'])));
    }

    public function test_missing_retry_after_falls_back_to_rate_limit_reset(): void {
        $client = $this->makeClient(new MockHandler);

        // OpenAI: kein Retry-After, aber x-ratelimit-reset-requests als Go-Dauer.
        $seconds = $client->exposeRetryAfterSeconds(new Response(429, ['x-ratelimit-reset-requests' => '1m30s']));
        $this->assertSame(90, $seconds);

        // Ohne jeden Hinweis weiterhin null (=> Backoff).
        $this->assertNull($client->exposeRetryAfterSeconds(new Response(429)));
    }

    public function test_rate_limit_snapshot_reads_ai_header_dialects(): void {
        $openai = RateLimit::fromResponse(new Response(200, [
            'x-ratelimit-limit-requests' => '5000',
            'x-ratelimit-remaining-requests' => '4999',
            'x-ratelimit-reset-requests' => '6m0s',
        ]));

        $this->assertNotNull($openai);
        $this->assertSame(5000, $openai->limit);
        $this->assertSame(4999, $openai->remaining);
        $this->assertEqualsWithDelta(360, $openai->secondsUntilReset(), 2);

        $anthropic = RateLimit::fromResponse(new Response(200, [
            'anthropic-ratelimit-requests-limit' => '50',
            'anthropic-ratelimit-requests-remaining' => '0',
            'anthropic-ratelimit-requests-reset' => gmdate('Y-m-d\TH:i:s\Z', time() + 45),
        ]));

        $this->assertNotNull($anthropic);
        $this->assertTrue($anthropic->isExhausted());
        $this->assertEqualsWithDelta(45, $anthropic->secondsUntilReset(), 2);
    }

    public function test_parse_delay_seconds_covers_the_dialects(): void {
        $this->assertSame(30, RateLimit::parseDelaySeconds('30'));
        $this->assertSame(1, RateLimit::parseDelaySeconds('0.5'));
        $this->assertSame(5400, RateLimit::parseDelaySeconds('1h30m'));
        $this->assertSame(1, RateLimit::parseDelaySeconds('220ms'));
        $this->assertSame(0, RateLimit::parseDelaySeconds(gmdate('D, d M Y H:i:s', time() - 60) . ' GMT'));
        $this->assertNull(RateLimit::parseDelaySeconds('not-a-date'));
        $this->assertNull(RateLimit::parseDelaySeconds(''));
    }
}

class QuotaDialectTestClient extends ClientAbstract {
    public function exposeRetryAfterSeconds(?ResponseInterface $response): ?int {
        return $this->retryAfterSeconds($response);
    }
}
