<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CursorPaginatorTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests\Pagination;

use APIToolkit\API\Pagination\{CursorPage, CursorPaginator};
use InvalidArgumentException;
use RuntimeException;
use Tests\Contracts\Test;

class CursorPaginatorTest extends Test {
    /**
     * @param array<?string, array{0: array, 1: ?string}> $pages cursor => [items, nextCursor]
     */
    private function fetcherFor(array $pages, ?array &$calls = null): callable {
        return function (?string $cursor) use ($pages, &$calls): CursorPage {
            $calls[] = $cursor;
            $key = $cursor ?? '';
            if (!array_key_exists($key, $pages)) {
                $this->fail("Unexpected cursor requested: " . var_export($cursor, true));
            }

            return new CursorPage($pages[$key][0], $pages[$key][1]);
        };
    }

    public function test_iterates_all_items_across_pages() {
        $calls = [];
        $paginator = new CursorPaginator($this->fetcherFor([
            '' => [['a', 'b'], 'c1'],
            'c1' => [['c'], 'c2'],
            'c2' => [['d'], null],
        ], $calls));

        $this->assertSame(['a', 'b', 'c', 'd'], $paginator->toArray());
        $this->assertSame([null, 'c1', 'c2'], $calls);
    }

    public function test_single_page_without_next_cursor() {
        $paginator = new CursorPaginator($this->fetcherFor([
            '' => [['only'], null],
        ]));

        $this->assertSame(['only'], iterator_to_array($paginator, false));
    }

    public function test_empty_next_cursor_string_ends_iteration() {
        $paginator = new CursorPaginator($this->fetcherFor([
            '' => [['x'], ''],
        ]));

        $this->assertSame(['x'], $paginator->toArray());
    }

    public function test_pages_generator_yields_page_objects() {
        $paginator = new CursorPaginator($this->fetcherFor([
            '' => [['a'], 'c1'],
            'c1' => [['b'], null],
        ]));

        $pages = iterator_to_array($paginator->pages(), false);

        $this->assertCount(2, $pages);
        $this->assertFalse($pages[0]->isLastPage());
        $this->assertTrue($pages[1]->isLastPage());
    }

    public function test_max_pages_limits_fetching() {
        $calls = [];
        $paginator = new CursorPaginator($this->fetcherFor([
            '' => [['a'], 'c1'],
            'c1' => [['b'], 'c2'],
            'c2' => [['c'], null],
        ], $calls), 2);

        $this->assertSame(['a', 'b'], $paginator->toArray());
        $this->assertSame([null, 'c1'], $calls);
    }

    public function test_non_advancing_cursor_throws() {
        $paginator = new CursorPaginator(function (?string $cursor): CursorPage {
            return new CursorPage(['loop'], 'same-cursor');
        });

        $this->expectException(RuntimeException::class);
        // Consume: first page yields, second page repeats the cursor.
        iterator_to_array($paginator, false);
    }

    public function test_invalid_max_pages_is_rejected() {
        $this->expectException(InvalidArgumentException::class);
        new CursorPaginator(fn (?string $c) => new CursorPage([]), 0);
    }
}
