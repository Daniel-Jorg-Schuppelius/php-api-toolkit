<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientLogRedactionTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use GuzzleHttp\{Client as HttpClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Log\{AbstractLogger, LoggerInterface};
use Tests\Contracts\Test;

class ClientLogRedactionTest extends Test {
    private ?LoggerInterface $previousLogger = null;

    /** @var AbstractLogger&object{records: array<int, array{level: mixed, message: string, context: array<string, mixed>}>} */
    private AbstractLogger $spyLogger;

    private MockHandler $mockHandler;

    protected function setUp(): void {
        parent::setUp();

        $this->previousLogger = ClientAbstract::getLogger();

        $this->spyLogger = new class extends AbstractLogger {
            /** @var array<int, array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }

    protected function tearDown(): void {
        // Der Konstruktor-Logger landet via setLogger() auch in der Registry —
        // ohne Restore würde der Spy in nachfolgende Testklassen durchsickern.
        ClientAbstract::setLogger($this->previousLogger);

        parent::tearDown();
    }

    private function makeClient(): ClientAbstract {
        $this->mockHandler = new MockHandler([new Response(200, [], '{}')]);
        $httpClient = new HttpClient(['handler' => HandlerStack::create($this->mockHandler)]);

        return new class('https://api.example.com', $this->spyLogger, false, $httpClient) extends ClientAbstract {
            /**
             * @param array<string, mixed> $options
             * @return array<string, mixed>
             */
            public function sanitizePublic(array $options): array {
                return $this->sanitizeOptionsForLog($options);
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function sendingRequestContext(): array {
        foreach ($this->spyLogger->records as $record) {
            if (str_starts_with($record['message'], 'Sending ')) {
                return $record['context'];
            }
        }

        $this->fail('No "Sending ... request" debug record captured');
    }

    public function test_debug_log_redacts_authorization_header_and_sensitive_query(): void {
        $client = $this->makeClient();

        $client->get('/sessions', [
            'headers' => ['Authorization' => 'Bearer super-secret-token', 'X-Api-Key' => 'key-123', 'Accept' => 'application/json'],
            'query' => ['api_key' => 'query-secret', 'from' => 1438981225],
        ]);

        $context = $this->sendingRequestContext();

        $this->assertSame('[redacted]', $context['headers']['Authorization']);
        $this->assertSame('[redacted]', $context['headers']['X-Api-Key']);
        $this->assertSame('application/json', $context['headers']['Accept']);
        $this->assertSame('[redacted]', $context['query']['api_key']);
        $this->assertSame(1438981225, $context['query']['from']);

        $allRecords = json_encode($this->spyLogger->records);
        $this->assertStringNotContainsString('super-secret-token', (string) $allRecords);
        $this->assertStringNotContainsString('query-secret', (string) $allRecords);
    }

    public function test_debug_log_redacts_form_params_and_auth_option(): void {
        $client = $this->makeClient();

        $client->post('/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'client_secret' => 'oauth-secret',
                'refresh_token' => 'refresh-me',
            ],
            'auth' => ['user', 'guzzle-pass'],
        ]);

        $context = $this->sendingRequestContext();

        $this->assertSame('refresh_token', $context['form_params']['grant_type']);
        $this->assertSame('[redacted]', $context['form_params']['client_secret']);
        $this->assertSame('[redacted]', $context['form_params']['refresh_token']);
        $this->assertSame('[redacted]', $context['auth']);

        $allRecords = json_encode($this->spyLogger->records);
        $this->assertStringNotContainsString('oauth-secret', (string) $allRecords);
        $this->assertStringNotContainsString('refresh-me', (string) $allRecords);
        $this->assertStringNotContainsString('guzzle-pass', (string) $allRecords);
    }

    public function test_wire_request_keeps_real_credentials(): void {
        $client = $this->makeClient();

        $client->get('/sessions', [
            'headers' => ['Authorization' => 'Bearer super-secret-token'],
        ]);

        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('Bearer super-secret-token', $lastRequest->getHeaderLine('Authorization'));
    }

    public function test_sanitize_is_case_insensitive_and_handles_string_query_and_nesting(): void {
        $client = $this->makeClient();

        $sanitized = $client->sanitizePublic([
            'headers' => ['AUTHORIZATION' => 'AD license:signature'],
            'query' => 'token=abc&page=2',
            'json' => ['nested' => ['Password' => 'pw', 'note' => 'keep']],
        ]);

        $this->assertSame('[redacted]', $sanitized['headers']['AUTHORIZATION']);
        $this->assertSame('[redacted]', $sanitized['query']['token']);
        $this->assertSame('2', $sanitized['query']['page']);
        $this->assertSame('[redacted]', $sanitized['json']['nested']['Password']);
        $this->assertSame('keep', $sanitized['json']['nested']['note']);
    }

    private function sendingRecord(): array {
        foreach ($this->spyLogger->records as $record) {
            if (str_starts_with($record['message'], 'Sending ')) {
                return $record;
            }
        }

        $this->fail('No "Sending ... request" debug record captured');
    }

    public function test_custom_auth_header_name_is_redacted(): void {
        $client = $this->makeClient();
        // A non-standard key header (e.g. GitLab PRIVATE-TOKEN) is not on the
        // static SENSITIVE_HEADERS allowlist but must still be redacted.
        $client->setAuthentication(new ApiKeyAuthentication('super-secret-key', 'PRIVATE-TOKEN'));

        $client->get('/projects');

        $context = $this->sendingRequestContext();
        $this->assertSame('[redacted]', $context['headers']['PRIVATE-TOKEN']);
        $this->assertStringNotContainsString('super-secret-key', (string) json_encode($this->spyLogger->records));
    }

    public function test_raw_string_body_is_not_logged_verbatim(): void {
        $client = $this->makeClient();

        $client->post('/login', ['body' => 'username=u&password=hunter2']);

        $context = $this->sendingRequestContext();
        $this->assertStringStartsWith('[raw body,', (string) $context['body']);
        $this->assertStringNotContainsString('hunter2', (string) json_encode($this->spyLogger->records));
    }

    public function test_secret_in_request_uri_query_is_redacted_in_message(): void {
        $client = $this->makeClient();

        $client->get('/resource?access_token=SECRETVALUE&page=2');

        $record = $this->sendingRecord();
        $this->assertStringNotContainsString('SECRETVALUE', $record['message']);
        $this->assertStringContainsString('page=2', $record['message']);
    }

    public function test_multipart_secret_field_is_redacted(): void {
        $client = $this->makeClient();

        $client->post('/upload', ['multipart' => [
            ['name' => 'file', 'contents' => 'binary-data'],
            ['name' => 'api_token', 'contents' => 'multipart-secret'],
        ]]);

        $this->assertStringNotContainsString('multipart-secret', (string) json_encode($this->spyLogger->records));
    }

    public function test_proxy_debug_log_strips_credentials(): void {
        $client = $this->makeClient();

        $client->setProxy('http://proxy-user:proxy-pass@proxy.local:8080');

        $messages = array_column($this->spyLogger->records, 'message');
        $this->assertContains('Proxy configured: http://proxy.local:8080', $messages);
        $this->assertStringNotContainsString('proxy-pass', (string) json_encode($this->spyLogger->records));

        $this->assertSame('http://proxy-user:proxy-pass@proxy.local:8080', $client->getProxy());
    }
}
