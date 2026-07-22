<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaginatorExtensionsTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\API\Pagination\{LinkHeaderPaginator, OffsetPaginator};
use GuzzleHttp\Psr7\Response;
use Tests\Contracts\Test;

class PaginatorExtensionsTest extends Test {
    public function test_offset_paginator_stops_on_short_page(): void {
        $pages = [
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7], // short page → last
        ];
        $calls = [];

        $paginator = new OffsetPaginator(function (int $page) use ($pages, &$calls): array {
            $calls[] = $page;

            return $pages[$page] ?? [];
        }, pageSize: 3);

        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $paginator->toArray());
        $this->assertSame([1, 2, 3], $calls, 'must stop after the short page, not fetch page 4');
    }

    public function test_offset_paginator_stops_on_empty_first_page(): void {
        $paginator = new OffsetPaginator(fn (int $page): array => [], pageSize: 50);
        $this->assertSame([], $paginator->toArray());
    }

    public function test_offset_paginator_respects_max_pages(): void {
        $paginator = new OffsetPaginator(fn (int $page): array => [$page * 10, $page * 10 + 1], pageSize: 2, startPage: 1, maxPages: 2);
        $this->assertSame([10, 11, 20, 21], $paginator->toArray());
    }

    public function test_link_header_paginator_follows_next_until_absent(): void {
        $responses = [
            null => new Response(200, ['Link' => '<https://api/items?page=2>; rel="next", <https://api/items?page=3>; rel="last"'], '[1,2]'),
            'https://api/items?page=2' => new Response(200, ['Link' => '<https://api/items?page=3>; rel="next"'], '[3,4]'),
            'https://api/items?page=3' => new Response(200, [], '[5]'),
        ];

        $paginator = new LinkHeaderPaginator(
            fn (?string $url): Response => $responses[$url] ?? new Response(200, [], '[]'),
            fn ($response): array => json_decode((string) $response->getBody(), true) ?: []
        );

        $this->assertSame([1, 2, 3, 4, 5], $paginator->toArray());
    }

    public function test_parse_next_link_extracts_only_next(): void {
        $header = '<https://api/a?page=1>; rel="prev", <https://api/a?page=3>; rel="next", <https://api/a?page=9>; rel="last"';
        $this->assertSame('https://api/a?page=3', LinkHeaderPaginator::parseNextLink($header));
        $this->assertNull(LinkHeaderPaginator::parseNextLink('<https://api/a?page=9>; rel="last"'));
        $this->assertNull(LinkHeaderPaginator::parseNextLink(''));
    }
}
