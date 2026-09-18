<?php
/*
 * Created on   : Fri Sep 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MultiStatus.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\WebDav;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Parser for WebDAV `207 Multi-Status` bodies (PROPFIND, REPORT).
 *
 * Mapping hrefs to local paths stays with the caller — servers differ in
 * base paths, encoding and trailing slashes.
 */
final class MultiStatus {
    private const DAV = 'DAV:';

    /**
     * @return list<MultiStatusResponse>
     * @throws InvalidArgumentException If the body is not well-formed XML.
     */
    public static function parse(string $xml): array {
        $doc = new DOMDocument;
        // No LIBXML_NOENT/DTDLOAD: entities stay unexpanded, no external loads (XXE).
        $previous = libxml_use_internal_errors(true);
        $loaded = trim($xml) !== '' && $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new InvalidArgumentException('Multi-status body is not well-formed XML');
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('d', self::DAV);

        $responses = [];
        foreach ($xpath->query('//d:response') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $href = trim((string) $xpath->evaluate('string(d:href)', $node));
            if ($href === '') {
                continue;
            }

            $properties = [];
            $isCollection = false;
            foreach ($xpath->query('d:propstat', $node) ?: [] as $propstat) {
                if (!$propstat instanceof DOMElement) {
                    continue;
                }
                $code = self::statusCode((string) $xpath->evaluate('string(d:status)', $propstat));
                if ($code === null || $code < 200 || $code >= 300) {
                    continue;
                }
                foreach ($xpath->query('d:prop/*', $propstat) ?: [] as $prop) {
                    if (!$prop instanceof DOMElement) {
                        continue;
                    }
                    $properties['{' . ($prop->namespaceURI ?? '') . '}' . $prop->localName] = $prop->textContent;
                    if ($prop->namespaceURI === self::DAV && $prop->localName === 'resourcetype'
                        && $prop->getElementsByTagNameNS(self::DAV, 'collection')->length > 0) {
                        $isCollection = true;
                    }
                }
            }

            $responses[] = new MultiStatusResponse(
                $href,
                self::statusCode((string) $xpath->evaluate('string(d:status)', $node)),
                $properties,
                $isCollection,
            );
        }

        return $responses;
    }

    /** "HTTP/1.1 404 Not Found" → 404. */
    private static function statusCode(string $statusLine): ?int {
        return preg_match('/^\s*HTTP\/\S+\s+(\d{3})\b/', $statusLine, $match) === 1 ? (int) $match[1] : null;
    }
}
