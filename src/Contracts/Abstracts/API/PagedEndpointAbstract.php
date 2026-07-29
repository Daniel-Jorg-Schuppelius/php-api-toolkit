<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PagedEndpointAbstract.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts\API;

use APIToolkit\API\Pagination\OffsetPaginator;
use Generator;

/**
 * Basis für Endpoints mit seitenweiser Suche.
 *
 * searchAll() läuft die Seiten über den {@see OffsetPaginator} durch und gibt
 * die einzelnen Einträge aus; Aufrufer führen weder Seitenzähler noch
 * Abbruchbedingung selbst. Wie eine Seite adressiert wird, entscheidet das
 * SDK über {@see pageQueryParams()} — seitennummern-basierte APIs
 * (page/size) und offset-basierte (limit/offset) unterscheiden sich nur darin.
 */
abstract class PagedEndpointAbstract extends EndpointAbstract {
    /** Obergrenze der Einträge je Anfrage; von der API vorgegeben. */
    public const MAX_PAGE_SIZE = 250;

    public const DEFAULT_PAGE_SIZE = 100;

    /** Nummer der ersten Seite (0 bei nullbasierten APIs). */
    protected const FIRST_PAGE = 0;

    /**
     * Query-Parameter für eine Seite.
     *
     * @param int $page Nullbasierter Seitenindex
     * @return array<string, mixed>
     */
    abstract protected function pageQueryParams(int $page, int $pageSize): array;

    /**
     * Die Einträge einer Seiten-Antwort.
     *
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $options
     * @return array<int, mixed>
     */
    abstract protected function pageItems(array $queryParams, array $options): array;

    /**
     * Iteriert alle Treffer einer Suche über sämtliche Seiten hinweg.
     *
     * @param array<string, mixed> $queryParams Suchparameter ohne Seitenangaben
     * @param array<string, mixed> $options
     * @param int|null $maxPages Obergrenze für die Zahl geladener Seiten
     * @return Generator<int, mixed>
     */
    public function searchAll(array $queryParams = [], int $pageSize = self::DEFAULT_PAGE_SIZE, array $options = [], ?int $maxPages = null): Generator {
        $pageSize = max(1, min($pageSize, static::MAX_PAGE_SIZE));

        $paginator = new OffsetPaginator(
            fn (int $page): array => $this->pageItems(
                array_merge($queryParams, $this->pageQueryParams($page, $pageSize)),
                $options
            ),
            $pageSize,
            static::FIRST_PAGE,
            $maxPages
        );

        yield from $paginator;
    }
}
