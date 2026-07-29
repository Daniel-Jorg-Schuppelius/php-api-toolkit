<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkHeaderTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Pagination;

use APIToolkit\API\Pagination\LinkHeader;
use GuzzleHttp\Psr7\Response;
use Tests\Contracts\Test;

class LinkHeaderTest extends Test {
    public function test_parses_every_relation(): void {
        $links = LinkHeader::parse('<https://api.example.com/items?page=2>; rel="next", <https://api.example.com/items?page=9>; rel="last"');

        $this->assertSame([
            'next' => 'https://api.example.com/items?page=2',
            'last' => 'https://api.example.com/items?page=9',
        ], $links);
    }

    public function test_parses_unquoted_and_spaced_relations(): void {
        $links = LinkHeader::parse('< https://api.example.com/items?page=2 > ; rel = next');

        $this->assertSame('https://api.example.com/items?page=2', $links['next'] ?? null);
    }

    public function test_relation_names_are_case_insensitive(): void {
        $this->assertSame(
            'https://api.example.com/items?page=2',
            LinkHeader::parse('<https://api.example.com/items?page=2>; rel="NEXT"')['next'] ?? null
        );
    }

    public function test_empty_and_malformed_headers_yield_nothing(): void {
        $this->assertSame([], LinkHeader::parse(''));
        $this->assertSame([], LinkHeader::parse('   '));
        $this->assertSame([], LinkHeader::parse('https://api.example.com/items?page=2; rel="next"'));
        $this->assertNull(LinkHeader::next(''));
    }

    public function test_parses_relative_targets_without_a_space_before_rel(): void {
        // Form der DATEV-Online-Dienste (accounting-clients)
        $links = LinkHeader::parse('<?skip=0&top=100>;rel="prev", <?skip=200&top=100>;rel="next"');

        $this->assertSame('?skip=0&top=100', $links['prev'] ?? null);
        $this->assertSame('?skip=200&top=100', $links['next'] ?? null);
    }

    public function test_reads_the_header_from_a_response(): void {
        $response = new Response(200, ['Link' => '<https://api.example.com/items?page=3>; rel="next"']);

        $this->assertSame('https://api.example.com/items?page=3', LinkHeader::fromResponse($response)['next'] ?? null);
    }
}
