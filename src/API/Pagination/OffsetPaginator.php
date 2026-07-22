<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OffsetPaginator.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Pagination;

use Closure;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * Transparently iterates offset/page-number paginated API results.
 *
 * The page fetcher is invoked with the current 1-based page number and returns
 * an array of that page's items. Iteration stops when a page returns fewer than
 * $pageSize items (the last page), when an empty page is returned, or when the
 * optional $maxPages limit is reached.
 *
 * Example:
 *
 *   $paginator = new OffsetPaginator(function (int $page) use ($client): array {
 *       $data = json_decode((string) $client->get("/items?page={$page}&per_page=100")->getBody(), true);
 *       return $data['results'] ?? [];
 *   }, pageSize: 100);
 *   foreach ($paginator as $item) { ... }
 *
 * @implements IteratorAggregate<int, mixed>
 */
class OffsetPaginator implements IteratorAggregate {
    protected Closure $pageFetcher;
    protected int $pageSize;
    protected int $startPage;
    protected ?int $maxPages;

    /**
     * @param callable(int): array<int, mixed> $pageFetcher Loads one page of items for the given page number
     * @param int $pageSize Expected items per full page; a shorter page ends iteration
     * @param int $startPage First page number (usually 1, some APIs use 0)
     * @param int|null $maxPages Optional hard limit on the number of fetched pages
     */
    public function __construct(callable $pageFetcher, int $pageSize = 100, int $startPage = 1, ?int $maxPages = null) {
        if ($pageSize < 1) {
            throw new InvalidArgumentException('Page size must be at least 1');
        }
        if ($maxPages !== null && $maxPages < 1) {
            throw new InvalidArgumentException('Max pages must be at least 1');
        }

        $this->pageFetcher = Closure::fromCallable($pageFetcher);
        $this->pageSize = $pageSize;
        $this->startPage = $startPage;
        $this->maxPages = $maxPages;
    }

    /**
     * @return Generator<int, mixed>
     */
    public function getIterator(): Generator {
        foreach ($this->pages() as $items) {
            foreach ($items as $item) {
                yield $item;
            }
        }
    }

    /**
     * Iterate page by page (each yield is one page's item array).
     *
     * @return Generator<int, array<int, mixed>>
     */
    public function pages(): Generator {
        $page = $this->startPage;
        $fetched = 0;

        do {
            $items = array_values(($this->pageFetcher)($page));

            if ($items === []) {
                break;
            }

            yield $items;

            $fetched++;
            $page++;

            // A short page is the last page.
            if (count($items) < $this->pageSize) {
                break;
            }
        } while ($this->maxPages === null || $fetched < $this->maxPages);
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array {
        return iterator_to_array($this, false);
    }
}
