<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkHeader.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Pagination;

use Psr\Http\Message\ResponseInterface;

/**
 * Parser für Link-Header nach RFC 8288 (vormals RFC 5988):
 * `<url1>; rel="next", <url2>; rel="last"`.
 *
 * Eine Implementierung für alle Nutzer — der {@see LinkHeaderPaginator} folgt
 * damit rel="next", SDKs reichen die übrigen Relationen an ihre Aufrufer
 * weiter.
 */
final class LinkHeader {
    /**
     * Alle Relationen eines Link-Headers.
     *
     * @return array<string, string> rel => URL
     */
    public static function parse(string $linkHeader): array {
        $links = [];

        if (trim($linkHeader) === '') {
            return $links;
        }

        foreach (explode(',', $linkHeader) as $part) {
            if (preg_match('/<\s*([^>]+?)\s*>\s*;\s*(.+)/', trim($part), $matches) !== 1) {
                continue;
            }

            if (preg_match('/rel\s*=\s*"?\s*([^";\s]+)\s*"?/i', $matches[2], $rel) !== 1) {
                continue;
            }

            $links[strtolower($rel[1])] = trim($matches[1]);
        }

        return $links;
    }

    /**
     * @return array<string, string> rel => URL
     */
    public static function fromResponse(ResponseInterface $response): array {
        return self::parse($response->getHeaderLine('Link'));
    }

    /** Ziel der Relation rel="next", oder null. */
    public static function next(string $linkHeader): ?string {
        return self::parse($linkHeader)['next'] ?? null;
    }
}
