<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkHeaderPaginator.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Pagination;

use Closure;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Psr\Http\Message\ResponseInterface;

/**
 * Transparently iterates API results paginated via RFC 8288 Link headers
 * (e.g. GitHub-style `Link: <…?page=2>; rel="next", <…?page=9>; rel="last"`).
 *
 * The page fetcher is invoked with the next URL to load (null for the first
 * page) and returns the PSR-7 response; an items extractor pulls the item list
 * out of that response. Iteration follows the `rel="next"` link until it is
 * absent.
 *
 * Example:
 *
 *   $paginator = new LinkHeaderPaginator(
 *       fn (?string $url) => $client->get($url ?? '/repos/acme/app/issues?per_page=100'),
 *       fn ($response) => json_decode((string) $response->getBody(), true) ?: []
 *   );
 *   foreach ($paginator as $issue) { ... }
 *
 * @implements IteratorAggregate<int, mixed>
 */
class LinkHeaderPaginator implements IteratorAggregate {
    protected Closure $pageFetcher;
    protected Closure $itemsExtractor;
    protected ?int $maxPages;

    /**
     * @param callable(?string): ResponseInterface $pageFetcher Loads the given URL (null = first page)
     * @param callable(ResponseInterface): array<int, mixed> $itemsExtractor Extracts the item list from a response
     * @param int|null $maxPages Optional hard limit on the number of fetched pages
     */
    public function __construct(callable $pageFetcher, callable $itemsExtractor, ?int $maxPages = null) {
        if ($maxPages !== null && $maxPages < 1) {
            throw new InvalidArgumentException('Max pages must be at least 1');
        }

        $this->pageFetcher = Closure::fromCallable($pageFetcher);
        $this->itemsExtractor = Closure::fromCallable($itemsExtractor);
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
     * @return Generator<int, array<int, mixed>>
     */
    public function pages(): Generator {
        $url = null;
        $fetched = 0;

        do {
            $response = ($this->pageFetcher)($url);
            yield array_values(($this->itemsExtractor)($response));

            $fetched++;
            $url = self::parseNextLink($response->getHeaderLine('Link'));
        } while ($url !== null && ($this->maxPages === null || $fetched < $this->maxPages));
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array {
        return iterator_to_array($this, false);
    }

    /**
     * Extract the rel="next" target from an RFC 8288 Link header, or null.
     */
    public static function parseNextLink(string $linkHeader): ?string {
        if ($linkHeader === '') {
            return null;
        }

        foreach (explode(',', $linkHeader) as $part) {
            if (preg_match('/<\s*([^>]+)\s*>\s*;\s*(.+)/', trim($part), $m) !== 1) {
                continue;
            }
            if (preg_match('/rel\s*=\s*"?\s*next\s*"?/i', $m[2]) === 1) {
                return trim($m[1]);
            }
        }

        return null;
    }
}
