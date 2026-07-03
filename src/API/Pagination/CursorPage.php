<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CursorPage.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Pagination;

/**
 * One page of a cursor-paginated API response.
 */
class CursorPage {
    /** @var array<int, mixed> */
    protected array $items;
    protected ?string $nextCursor;

    /**
     * @param array<int, mixed> $items Items of this page
     * @param string|null $nextCursor Cursor of the next page; null on the last page
     */
    public function __construct(array $items, ?string $nextCursor = null) {
        $this->items = array_values($items);
        $this->nextCursor = $nextCursor === '' ? null : $nextCursor;
    }

    /**
     * @return array<int, mixed>
     */
    public function getItems(): array {
        return $this->items;
    }

    public function getNextCursor(): ?string {
        return $this->nextCursor;
    }

    public function isLastPage(): bool {
        return $this->nextCursor === null;
    }
}
