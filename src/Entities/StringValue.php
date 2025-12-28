<?php
/*
 * Created on   : Sun Dec 28 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StringValue.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class StringValue extends NamedValue {
    protected ?int $minLength = null;
    protected ?int $maxLength = null;
    protected ?string $pattern = null;

    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function isValid(): bool {
        if (!is_string($this->value) && !is_null($this->value)) {
            return false;
        }

        if (is_null($this->value)) {
            return true;
        }

        if ($this->minLength !== null && strlen($this->value) < $this->minLength) {
            return false;
        }

        if ($this->maxLength !== null && strlen($this->value) > $this->maxLength) {
            return false;
        }

        if ($this->pattern !== null && !preg_match($this->pattern, $this->value)) {
            return false;
        }

        return true;
    }

    public function isEmpty(): bool {
        return empty($this->value);
    }

    public function isBlank(): bool {
        return empty(trim($this->value ?? ''));
    }

    public function length(): int {
        return strlen($this->value ?? '');
    }

    public function trim(): self {
        if (is_string($this->value)) {
            $this->value = trim($this->value);
        }
        return $this;
    }

    public function toLowerCase(): self {
        if (is_string($this->value)) {
            $this->value = strtolower($this->value);
        }
        return $this;
    }

    public function toUpperCase(): self {
        if (is_string($this->value)) {
            $this->value = strtoupper($this->value);
        }
        return $this;
    }

    public function contains(string $needle, bool $caseSensitive = true): bool {
        if (!is_string($this->value)) {
            return false;
        }

        if ($caseSensitive) {
            return str_contains($this->value, $needle);
        }

        return str_contains(strtolower($this->value), strtolower($needle));
    }

    public function startsWith(string $prefix, bool $caseSensitive = true): bool {
        if (!is_string($this->value)) {
            return false;
        }

        if ($caseSensitive) {
            return str_starts_with($this->value, $prefix);
        }

        return str_starts_with(strtolower($this->value), strtolower($prefix));
    }

    public function endsWith(string $suffix, bool $caseSensitive = true): bool {
        if (!is_string($this->value)) {
            return false;
        }

        if ($caseSensitive) {
            return str_ends_with($this->value, $suffix);
        }

        return str_ends_with(strtolower($this->value), strtolower($suffix));
    }

    public function matches(string $pattern): bool {
        if (!is_string($this->value)) {
            return false;
        }

        return (bool) preg_match($pattern, $this->value);
    }

    public function truncate(int $length, string $suffix = '...'): string {
        if (!is_string($this->value) || strlen($this->value) <= $length) {
            return $this->value ?? '';
        }

        return substr($this->value, 0, $length - strlen($suffix)) . $suffix;
    }
}