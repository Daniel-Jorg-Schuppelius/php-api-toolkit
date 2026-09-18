<?php
/*
 * Created on   : Fri Sep 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Propfind.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\WebDav;

use InvalidArgumentException;

/**
 * Request body for a WebDAV PROPFIND (RFC 4918 §9.1).
 *
 * Example:
 * ```php
 * $body = Propfind::body(['d:getetag', 'oc:fileid'], ['oc' => 'http://owncloud.org/ns']);
 * $client->requestResponse('PROPFIND', $url, ['headers' => ['Depth' => '1'], 'body' => $body]);
 * ```
 */
final class Propfind {
    /**
     * @param list<string> $properties Qualified property names such as `d:getetag`.
     * @param array<string, string> $namespaces Prefix => namespace URI; `d` => `DAV:` is always declared.
     * @throws InvalidArgumentException On malformed names, unknown prefixes or an empty list.
     */
    public static function body(array $properties, array $namespaces = []): string {
        if ($properties === []) {
            throw new InvalidArgumentException('PROPFIND needs at least one property');
        }

        $namespaces = ['d' => 'DAV:'] + $namespaces;
        foreach ($namespaces as $prefix => $uri) {
            if (preg_match('/^[A-Za-z_][\w.-]*$/D', (string) $prefix) !== 1 || $uri === '') {
                throw new InvalidArgumentException("Invalid namespace declaration: $prefix");
            }
        }

        $props = '';
        foreach ($properties as $property) {
            if (preg_match('/^([A-Za-z_][\w.-]*):[A-Za-z_][\w.-]*$/D', $property, $match) !== 1) {
                throw new InvalidArgumentException("Invalid property name: $property");
            }
            if (!isset($namespaces[$match[1]])) {
                throw new InvalidArgumentException("Undeclared namespace prefix: {$match[1]}");
            }
            $props .= '<' . $property . '/>';
        }

        $declarations = '';
        foreach ($namespaces as $prefix => $uri) {
            $declarations .= ' xmlns:' . $prefix . '="' . htmlspecialchars($uri, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind' . $declarations . '><d:prop>' . $props . '</d:prop></d:propfind>';
    }
}
