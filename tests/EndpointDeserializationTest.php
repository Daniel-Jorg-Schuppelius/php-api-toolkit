<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EndpointDeserializationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\EndpointAbstract;
use APIToolkit\Contracts\Interfaces\API\ApiClientInterface;
use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use APIToolkit\Entities\Bank\IBAN;
use APIToolkit\Entities\ID;
use GuzzleHttp\Psr7\Response;
use Tests\Contracts\Test;

class EndpointDeserializationTest extends Test {
    private function endpoint(ApiClientInterface $client): object {
        return new class($client) extends EndpointAbstract {
            protected string $endpoint = 'ibans';

            public function get(?ID $id = null): ?NamedEntityInterface {
                return $this->fetchIban();
            }

            public function fetchIban(): IBAN {
                return $this->getEntity(IBAN::class);
            }

            /** @return array<int|string, mixed> */
            public function fetchArray(): array {
                return $this->getArray();
            }

            /** @param array<int, array<string, mixed>> $files */
            public function upload(array $fields, array $files): string {
                return $this->postMultipart($fields, $files);
            }
        };
    }

    public function test_get_entity_hydrates_response_into_entity(): void {
        $client = $this->createMock(ApiClientInterface::class);
        $client->method('get')->willReturn(new Response(200, [], '{"iban":"DE44500105175407324931"}'));

        $iban = $this->endpoint($client)->fetchIban();

        $this->assertInstanceOf(IBAN::class, $iban);
        $this->assertSame('DE44500105175407324931', $iban->getValue());
        $this->assertTrue($iban->isValid());
    }

    public function test_get_array_decodes_json_body(): void {
        $client = $this->createMock(ApiClientInterface::class);
        $client->method('get')->willReturn(new Response(200, [], '{"results":[1,2,3]}'));

        $this->assertSame(['results' => [1, 2, 3]], $this->endpoint($client)->fetchArray());
    }

    public function test_post_multipart_builds_parts_and_returns_body(): void {
        $client = $this->createMock(ApiClientInterface::class);
        $captured = null;
        $client->method('post')->willReturnCallback(function (string $uri, array $options) use (&$captured): Response {
            $captured = $options;

            return new Response(201, [], '{"ok":true}');
        });

        $body = $this->endpoint($client)->upload(
            ['title' => 'invoice'],
            [['name' => 'file', 'contents' => 'PDFDATA', 'filename' => 'a.pdf']]
        );

        $this->assertSame('{"ok":true}', $body);
        $this->assertArrayHasKey('multipart', $captured);
        $this->assertSame('title', $captured['multipart'][0]['name']);
        $this->assertSame('file', $captured['multipart'][1]['name']);
        $this->assertSame('a.pdf', $captured['multipart'][1]['filename']);
    }
}
