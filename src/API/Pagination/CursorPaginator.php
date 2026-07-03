<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CursorPaginator.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Pagination;

use Closure;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use RuntimeException;

/**
 * Transparently iterates cursor-paginated API results.
 *
 * The page fetcher is invoked with the current cursor (null for the first
 * page) and returns a CursorPage. Iteration stops when the page reports no
 * next cursor. A cursor that does not advance (same value twice in a row)
 * aborts with a RuntimeException — guard against endless pagination loops.
 *
 * Example:
 *
 *   $paginator = new CursorPaginator(function (?string $cursor) use ($client): CursorPage {
 *       $data = json_decode((string) $client->get('/tasks' . ($cursor !== null ? "?cursor={$cursor}" : ''))->getBody(), true);
 *       return new CursorPage($data['results'], $data['next_cursor'] ?? null);
 *   });
 *   foreach ($paginator as $task) { ... }
 *
 * @implements IteratorAggregate<int, mixed>
 */
class CursorPaginator implements IteratorAggregate {
    protected Closure $pageFetcher;
    protected ?int $maxPages;

    /**
     * @param callable(?string): CursorPage $pageFetcher Loads one page for the given cursor
     * @param int|null $maxPages Optional hard limit on the number of fetched pages
     */
    public function __construct(callable $pageFetcher, ?int $maxPages = null) {
        if ($maxPages !== null && $maxPages < 1) {
            throw new InvalidArgumentException('Max pages must be at least 1');
        }

        $this->pageFetcher = Closure::fromCallable($pageFetcher);
        $this->maxPages = $maxPages;
    }

    /**
     * Iterate over all items across all pages.
     *
     * @return Generator<int, mixed>
     */
    public function getIterator(): Generator {
        foreach ($this->pages() as $page) {
            foreach ($page->getItems() as $item) {
                yield $item;
            }
        }
    }

    /**
     * Iterate page by page (e.g. for chunked processing).
     *
     * @return Generator<int, CursorPage>
     */
    public function pages(): Generator {
        $cursor = null;
        $pageCount = 0;

        do {
            $page = ($this->pageFetcher)($cursor);

            if (!$page instanceof CursorPage) {
                throw new RuntimeException('Page fetcher must return a CursorPage instance.');
            }

            yield $page;

            $pageCount++;
            $nextCursor = $page->getNextCursor();

            if ($nextCursor !== null && $nextCursor === $cursor) {
                throw new RuntimeException('Pagination cursor did not advance — aborting to avoid an endless loop.');
            }

            $cursor = $nextCursor;
        } while ($cursor !== null && ($this->maxPages === null || $pageCount < $this->maxPages));
    }

    /**
     * Collect all items into an array (loads every page — mind the memory).
     *
     * @return array<int, mixed>
     */
    public function toArray(): array {
        return iterator_to_array($this, false);
    }
}
