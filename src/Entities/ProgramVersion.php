<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProgramVersion.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities;

use Psr\Log\LoggerInterface;

class ProgramVersion extends Version {
    // SemVer Regex: v1.0.0, 1.0.0, 1.0.0-alpha, 1.0.0-beta.1, 1.0.0+build, 1.0a (suffix)
    private const SEMVER_PATTERN = '/^v?(\\d+)(?:\\.(\\d+))?(?:\\.(\\d+))?([a-z])?(?:-([a-zA-Z0-9.-]+))?(?:\\+([a-zA-Z0-9.-]+))?$/';

    private ?int $major = null;
    private ?int $minor = null;
    private ?int $patch = null;
    private ?string $preRelease = null;
    private ?string $buildMetadata = null;

    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        if (is_null($data)) {
            $data = 'v0.0.0';
        }
        parent::__construct($data, $logger);
        $this->entityName = 'version';
        $this->parseVersion();
    }

    public function isValid(): bool {
        if (!isset($this->value)) {
            return false;
        }

        // Numerische Version (Fallback von Version)
        if (is_numeric($this->value) && $this->value >= 0) {
            return true;
        }

        // SemVer String
        if (is_string($this->value)) {
            return (bool) preg_match(self::SEMVER_PATTERN, $this->value);
        }

        return false;
    }

    private function parseVersion(): void {
        if (!is_string($this->value)) {
            return;
        }

        if (preg_match(self::SEMVER_PATTERN, $this->value, $matches)) {
            $this->major = (int) ($matches[1] ?? 0);
            $this->minor = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
            $this->patch = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
            // matches[4] ist jetzt das optionale Suffix wie 'a' in "1.0a"
            $suffix = $matches[4] ?? null;
            $this->preRelease = !empty($matches[5]) ? $matches[5] : ($suffix ?: null);
            $this->buildMetadata = $matches[6] ?? null;
        }
    }

    public function getMajor(): ?int {
        return $this->major;
    }

    public function getMinor(): ?int {
        return $this->minor;
    }

    public function getPatch(): ?int {
        return $this->patch;
    }

    public function getPreRelease(): ?string {
        return $this->preRelease;
    }

    public function getBuildMetadata(): ?string {
        return $this->buildMetadata;
    }

    public function isPreRelease(): bool {
        return $this->preRelease !== null;
    }

    public function compareTo(Version $other): int {
        if (!$other instanceof ProgramVersion) {
            return parent::compareTo($other);
        }

        // Major vergleichen
        $result = ($this->major ?? 0) <=> ($other->getMajor() ?? 0);
        if ($result !== 0) {
            return $result;
        }

        // Minor vergleichen
        $result = ($this->minor ?? 0) <=> ($other->getMinor() ?? 0);
        if ($result !== 0) {
            return $result;
        }

        // Patch vergleichen
        $result = ($this->patch ?? 0) <=> ($other->getPatch() ?? 0);
        if ($result !== 0) {
            return $result;
        }

        // Pre-Release: keine Pre-Release > mit Pre-Release
        if ($this->preRelease === null && $other->getPreRelease() !== null) {
            return 1;
        }
        if ($this->preRelease !== null && $other->getPreRelease() === null) {
            return -1;
        }

        // Beide haben Pre-Release: alphabetisch vergleichen
        return ($this->preRelease ?? '') <=> ($other->getPreRelease() ?? '');
    }

    public function getNormalized(): string {
        $version = sprintf('%d.%d.%d', $this->major ?? 0, $this->minor ?? 0, $this->patch ?? 0);

        if ($this->preRelease !== null) {
            $version .= '-' . $this->preRelease;
        }

        if ($this->buildMetadata !== null) {
            $version .= '+' . $this->buildMetadata;
        }

        return $version;
    }
}
