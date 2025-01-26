<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedValue.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use APIToolkit\Contracts\Interfaces\NamedValueInterface;
use ERRORToolkit\Traits\ErrorLog;
use DateTime;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class NamedValue implements NamedValueInterface {
    use ErrorLog;

    protected string $entityName;
    protected $value;

    protected bool $readOnly = false;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        $this->logger = $logger;

        if (empty($this->entityName))
            $this->entityName = static::class;

        $this->value = $this->validateData($data);
    }

    public function getEntityName(): string {
        return $this->entityName;
    }

    public function getValue(): mixed {
        return $this->value ?? null;
    }

    public function setData($data): NamedEntityInterface {
        if ($this->readOnly) {
            $this->logError("Cannot modify read-only value.");
            throw new RuntimeException("Cannot modify read-only value.");
        }
        $this->value = $this->validateData($data);
        return $this;
    }

    public function isReadOnly(): bool {
        return $this->readOnly;
    }

    public function isValid(): bool {
        return true;
    }

    protected function validateData($data): mixed {
        if (is_array($data) && count($data) == 1) {
            foreach ($data as $key => $val) {
                if ($key != $this->entityName) {
                    $this->logError("Name $key does not match entity name $this->entityName.");
                    throw new InvalidArgumentException("Name $key does not exist.");
                }
                return $val;
            }
        } elseif (is_array($data) && empty($data)) {
            return null;
        } elseif (!is_scalar($data) && !is_null($data)) {
            $this->logError("Value must be a scalar or null.");
            throw new InvalidArgumentException("Value must be a scalar or null.");
        }
        return $data;
    }

    public function equals(NamedEntityInterface $other): bool {
        if (get_class($this) !== get_class($other)) {
            return false;
        }

        if ($this instanceof NamedValueInterface && $other instanceof NamedValueInterface) {
            $thisValue = $this->getValue();
            $otherValue = $other->getValue();

            if ($thisValue instanceof NamedEntityInterface && $otherValue instanceof NamedEntityInterface) {
                return $thisValue->equals($otherValue);
            }

            return $thisValue === $otherValue;
        }
        return false;
    }

    public function toArray(): array {
        return $this->getArray();
    }

    protected function getArray(bool $asStringValues = false, bool $dateAsStringValue = true, string $dateFormat = DateTime::RFC3339_EXTENDED): array {
        $result = [];
        if (is_array($this->value)) {
            foreach ($this->value as $key => $value) {
                $result[] = $this->makeArray($key, $value, $asStringValues, $dateAsStringValue, $dateFormat);
            }
        } else {
            $result[$this->entityName] = $this->makeArray($this->entityName, $this->value, $asStringValues,  $dateAsStringValue, $dateFormat)[$this->entityName];
        }
        return $result;
    }

    protected function makeArray($key, $value, bool $asStringValues, bool $dateAsStringValues, string $dateFormat): array {
        $result = [];

        if ($value instanceof NamedEntityInterface) {
            $result[] = $value->toArray();
        } elseif ($value instanceof DateTime) {
            $result[$key] = $dateAsStringValues ? $value["value"]->format($dateFormat) : $value["value"];
        } elseif (is_scalar($value)) {
            $result[$key] = $asStringValues ? (string)$value : $value;
        } else {
            $result[$key] = $value;
        }

        return $result;
    }

    public function toJson(int $flags = 0): string {
        return json_encode($this->toArray(), $flags);
    }

    public function toString(): string {
        return (string) $this->value;
    }

    public static function fromArray(array $data, ?LoggerInterface $logger = null): self {
        $className = get_called_class();
        return new $className($data, $logger);
    }

    public static function fromJson(string $data, ?LoggerInterface $logger = null): self {
        return self::fromArray(json_decode($data, true), $logger);
    }
}
