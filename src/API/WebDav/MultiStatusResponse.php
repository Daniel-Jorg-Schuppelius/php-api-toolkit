<?php
/*
 * Created on   : Fri Sep 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MultiStatusResponse.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\WebDav;

/**
 * One `<d:response>` of a WebDAV multi-status body (RFC 4918 §13).
 *
 * Properties from propstat blocks with an explicit non-2xx status are
 * dropped: servers report requested-but-missing properties in a 404 propstat
 * with an empty value, which must not shadow real values. A propstat without
 * status (RFC 4918 requires one, some servers omit it) counts as successful.
 */
final class MultiStatusResponse {
    /**
     * @param string $href The resource href as sent by the server (trimmed, not decoded).
     * @param int|null $status Response-level status (e.g. 404 in a sync-collection report), null if absent.
     * @param array<string, string> $properties Clark notation `{namespace}name` => text value.
     * @param bool $isCollection Whether `resourcetype` contains `DAV:collection`.
     */
    public function __construct(
        public readonly string $href,
        public readonly ?int $status,
        public readonly array $properties,
        public readonly bool $isCollection,
    ) {}

    /**
     * Text value of a property, or null if it was not reported successfully.
     */
    public function property(string $namespace, string $name): ?string {
        return $this->properties['{' . $namespace . '}' . $name] ?? null;
    }

    /**
     * The entity tag without surrounding quotes and whitespace, or null.
     */
    public function etag(): ?string {
        $etag = $this->property('DAV:', 'getetag');
        if ($etag === null) {
            return null;
        }
        $etag = trim($etag, " \t\n\r\0\x0B\"");

        return $etag === '' ? null : $etag;
    }

    /**
     * Whether the resource is reported as removed (404/410) — used by
     * sync-collection reports to announce deletions.
     */
    public function isGone(): bool {
        return $this->status === 404 || $this->status === 410;
    }
}
