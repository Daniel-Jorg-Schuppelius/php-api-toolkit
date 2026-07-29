<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MockApiClientTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Testing;

use APIToolkit\Testing\MockApiClient;
use Tests\Contracts\Test;

class MockApiClientTest extends Test {
    public function test_registered_response_is_returned_and_logged(): void {
        $client = (new MockApiClient)->addResponse('GET', 'article/90', 200, '{"id":90}');

        $response = $client->get('article/90', ['query' => ['x' => 1]]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"id":90}', (string) $response->getBody());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $last = $client->getLastRequest();
        $this->assertNotNull($last);
        $this->assertSame('GET', $last['method']);
        $this->assertSame('article/90', $last['uri']);
        $this->assertSame(['query' => ['x' => 1]], $last['options']);
        $this->assertCount(1, $client->getRequestLog());
    }

    public function test_wildcards_match_uris_and_methods(): void {
        $client = (new MockApiClient)
            ->addResponse('GET', 'article/*', 200, '{"matched":"uri-wildcard"}')
            ->addResponse('*', 'invoice/7', 201, '{"matched":"method-wildcard"}');

        $this->assertSame('{"matched":"uri-wildcard"}', (string) $client->get('article/123')->getBody());
        $this->assertSame('{"matched":"method-wildcard"}', (string) $client->put('invoice/7')->getBody());
    }

    public function test_specific_method_wins_over_wildcard(): void {
        $client = (new MockApiClient)
            ->addResponse('*', 'invoice/7', 200, '{"from":"wildcard"}')
            ->addResponse('DELETE', 'invoice/7', 204, '');

        $this->assertSame(204, $client->delete('invoice/7')->getStatusCode());
        $this->assertSame(200, $client->post('invoice/7')->getStatusCode());
    }

    public function test_unmatched_uri_falls_back_to_the_default_response(): void {
        $client = (new MockApiClient)->setDefaultResponse(404, '{"message":"not found"}');

        $response = $client->patch('unbekannt');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"message":"not found"}', (string) $response->getBody());
    }

    public function test_log_and_responses_can_be_cleared(): void {
        $client = (new MockApiClient)->addResponse('GET', 'ping', 200, '{"pong":true}');
        $client->get('ping');

        $client->clearRequestLog();
        $this->assertSame([], $client->getRequestLog());
        $this->assertNull($client->getLastRequest());

        $client->clearResponses();
        $this->assertSame('{}', (string) $client->get('ping')->getBody());
    }
}
