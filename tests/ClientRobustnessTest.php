<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientRobustnessTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests;

use APIToolkit\API\Authentication\OAuth2\{InMemoryTokenStore, OAuth2AuthorizationCodeGrant, OAuth2BearerAuthentication, OAuth2Token};
use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Exceptions\UnauthorizedException;
use DateTimeImmutable;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Request, Response};
use Tests\Contracts\Test;

class ClientRobustnessTest extends Test {
    private function makeClient(MockHandler $mock): ClientAbstract {
        $httpClient = new HttpClient(['handler' => HandlerStack::create($mock)]);

        return new class('https://api.example.com', null, false, $httpClient) extends ClientAbstract {};
    }

    public function test_default_query_params_do_not_discard_uri_query() {
        $mock = new MockHandler([new Response(200)]);
        $client = $this->makeClient($mock);
        $client->setDefaultQueryParams(['api_version' => 'v1']);

        $client->get('/tasks?cursor=abc&limit=50');

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('v1', $query['api_version']);
        $this->assertSame('abc', $query['cursor']);
        $this->assertSame('50', $query['limit']);
    }

    public function test_explicit_query_option_beats_uri_and_defaults() {
        $mock = new MockHandler([new Response(200)]);
        $client = $this->makeClient($mock);
        $client->setDefaultQueryParams(['limit' => '10']);

        $client->get('/tasks?limit=20', ['query' => ['limit' => '30']]);

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('30', $query['limit']);
    }

    public function test_connection_errors_are_retried() {
        $mock = new MockHandler([
            new ConnectException('DNS failure', new Request('GET', '/resource')),
            new Response(200, [], 'ok'),
        ]);
        $client = $this->makeClient($mock);
        $client->setBaseRetryDelay(1);

        $response = $client->get('/resource');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $mock->count());
    }

    public function test_connection_errors_are_rethrown_after_max_retries() {
        $mock = new MockHandler([
            new ConnectException('down', new Request('GET', '/r')),
            new ConnectException('down', new Request('GET', '/r')),
            new ConnectException('down', new Request('GET', '/r')),
        ]);
        $client = $this->makeClient($mock);

        $this->expectException(ConnectException::class);
        $client->get('/r');
    }

    public function test_set_base_url_keeps_injected_http_client() {
        $mock = new MockHandler([new Response(200, [], 'ok')]);
        $client = $this->makeClient($mock);

        $client->setBaseUrl('https://other.example.com');

        // Still served by the injected mock — the client was not replaced.
        $response = $client->get('/anything');
        $this->assertSame('ok', (string) $response->getBody());
        $this->assertSame('https://other.example.com', $client->getBaseUrl());
    }

    public function test_unauthorized_triggers_one_refresh_and_retry() {
        $tokenEndpoint = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_token' => 'fresh-at',
                'expires_in' => 3600,
            ])),
        ]);
        $grant = new OAuth2AuthorizationCodeGrant(
            'client-id',
            'client-secret',
            'https://provider.example.com/oauth/authorize',
            'https://provider.example.com/oauth/access_token',
            null,
            null,
            new HttpClient(['handler' => HandlerStack::create($tokenEndpoint)])
        );
        $store = new InMemoryTokenStore(new OAuth2Token('stale-at', 'rt', new DateTimeImmutable('+2 hours')));

        $api = new MockHandler([
            new Response(401),
            new Response(200, [], 'ok'),
        ]);
        $client = $this->makeClient($api);
        $client->setAuthentication(new OAuth2BearerAuthentication($store, $grant));

        $response = $client->get('/tasks');

        $this->assertSame(200, $response->getStatusCode());
        $request = $api->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('Bearer fresh-at', $request->getHeaderLine('Authorization'));
    }

    public function test_unauthorized_without_refresh_possibility_propagates() {
        $api = new MockHandler([new Response(401)]);
        $client = $this->makeClient($api);
        $client->setAuthentication(new OAuth2BearerAuthentication(new InMemoryTokenStore(new OAuth2Token('at'))));

        $this->expectException(UnauthorizedException::class);
        $client->get('/tasks');
    }

    public function test_second_unauthorized_after_refresh_propagates() {
        $tokenEndpoint = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_token' => 'fresh-at',
            ])),
        ]);
        $grant = new OAuth2AuthorizationCodeGrant(
            'client-id',
            'client-secret',
            'https://provider.example.com/oauth/authorize',
            'https://provider.example.com/oauth/access_token',
            null,
            null,
            new HttpClient(['handler' => HandlerStack::create($tokenEndpoint)])
        );
        $store = new InMemoryTokenStore(new OAuth2Token('stale-at', 'rt'));

        $api = new MockHandler([
            new Response(401),
            new Response(401),
        ]);
        $client = $this->makeClient($api);
        $client->setAuthentication(new OAuth2BearerAuthentication($store, $grant));

        $this->expectException(UnauthorizedException::class);
        $client->get('/tasks');
    }
}
