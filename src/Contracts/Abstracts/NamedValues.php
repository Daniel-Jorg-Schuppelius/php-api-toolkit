<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedValues.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use APIToolkit\Contracts\Interfaces\NamedValueInterface;
use APIToolkit\Contracts\Interfaces\NamedValuesInterface;
use APIToolkit\Enums\ComparisonType;
use DateTime;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class NamedValues implements NamedValuesInterface {
    protected ?LoggerInterface $logger;

    protected string $valueClassName = string::class;
    protected string $entityName;
    protected array $values = [];

    protected bool $readOnly = false;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        $this->logger = $logger;

        if (!empty($data) && isset($this->entityName) && $this->entityName == "content" && array_key_exists($this->entityName, $data)) {
            $this->values = $this->validateData($data[$this->entityName]);
        } else {
            $this->values = $this->validateData($data);
        }
    }

    public function getEntityName(): string {
        return $this->entityName;
    }

    public function getEntities(?string $propertyName = null, $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): NamedValues {
        $className = get_called_class();

        return new $className($this->getValues($propertyName, $searchValue, $comparisonType));
    }

    public function getValues(?string $propertyName = null, $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): array {
        if (is_null($propertyName)) {
            return $this->values;
        } else {
            $result = $this->searchData($propertyName, $searchValue, $comparisonType);
            if (is_null($result)) {
                return [];
            }

            return $result;
        }
    }

    public function isReadOnly(): bool {
        return $this->readOnly;
    }

    public function isValid(): bool {
        foreach ($this->values as $value) {
            if ($value instanceof NamedEntityInterface && !$value->isValid()) {
                return false;
            }
        }

        return true;
    }

    public function setData($data): NamedEntityInterface {
        if ($this->readOnly) {
            throw new RuntimeException("Cannot modify read-only value.");
        }
        $this->values = $this->validateData($data);
        return $this;
    }

    protected function searchData(string $propertyName, $searchValue, ComparisonType $comparisonType = ComparisonType::EQUALS): array {
        $result = [];

        foreach ($this->values as $value) {
            if ($value instanceof NamedEntityInterface) {
                $propertyValue = null;

                $reflectionClass = new \ReflectionClass($value);
                if ($reflectionClass->hasProperty($propertyName)) {
                    $property = $reflectionClass->getProperty($propertyName);

                    // Überprüfen, ob die Property initialisiert ist (verwendet Reflection)
                    if ($property->isInitialized($value)) {
                        $propertyValue = $property->getValue($value);
                    } else {
                        // Überspringe, falls die Property nicht initialisiert ist
                        continue;
                    }
                }

                // Überprüfen, ob ein Wert gefunden wurde
                if (is_null($propertyValue)) {
                    continue;
                } elseif ($propertyValue instanceof NamedValueInterface) {
                    $propertyValue = $propertyValue->getValue();
                }

                // Vergleich basierend auf dem übergebenen Vergleichstyp
                switch ($comparisonType) {
                    case ComparisonType::EQUALS:
                        if ($propertyValue == $searchValue) {
                            $result[] = $value;
                        }
                        break;
                    case ComparisonType::CONTAINS:
                        if (is_string($propertyValue) && strpos($propertyValue, $searchValue) !== false) {
                            $result[] = $value;
                        }
                        break;
                    case ComparisonType::GREATER_THAN:
                        if (is_numeric($propertyValue) && $propertyValue > $searchValue) {
                            $result[] = $value;
                        }
                        break;
                    case ComparisonType::LESS_THAN:
                        if (is_numeric($propertyValue) && $propertyValue < $searchValue) {
                            $result[] = $value;
                        }
                        break;
                    case ComparisonType::REGEX:
                        if (is_string($propertyValue) && preg_match($searchValue, $propertyValue)) {
                            $result[] = $value;
                        }
                        break;
                    default:
                        throw new InvalidArgumentException("Unsupported comparison type: $comparisonType");
                }
            }
        }

        return $result;
    }

    protected function validateData($data): array {
        $result = [];
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_scalar($item) || is_array($item) || is_null($item)) {
                    if (is_subclass_of($this->valueClassName, NamedEntityInterface::class)) {
                        $result[] = new $this->valueClassName($item, $this->logger);
                    } else {
                        $result[] = new $this->valueClassName($item);
                    }
                } elseif (is_object($item) && $item instanceof NamedEntity && ($item->getEntityName() == $this->valueClassName)) {
                    $result[] = $item;
                } else {
                    throw new InvalidArgumentException("Value must be an array of scalars, or a nested array.");
                }
            }
        } else {
            $result[] = $data;
        }
        return $result;
    }

    protected function isArrayFullyNumeric(array $data): bool {
        $keys = array_keys($data);

        $nonNumericKeys = array_filter($keys, function ($key) {
            return !is_int($key);
        });

        return count($nonNumericKeys) === 0;
    }

    protected function isArrayOfNumericValues(array $data, bool $isKeysNumeric = true): bool {
        if ($isKeysNumeric && !$this->isArrayFullyNumeric($data)) {
            return false;
        }

        foreach ($data as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }
        return true;
    }

    protected function getArray(bool $asStringValues = false, bool $dateAsStringValues = true, string $dateFormat = DateTime::RFC3339_EXTENDED): array {
        $result = [];
        foreach ($this->values as $key => $value) {
            $temp = $this->makeArray($key, $value, $asStringValues, $dateAsStringValues, $dateFormat);
            if ($value instanceof NamedValueInterface && $value->getEntityName() == $this->valueClassName) {
                $result[] = array_pop($temp);
            } else {
                $result[] = $temp;
            }
        }
        return $result;
    }

    protected function makeArray($key, $value, bool $asStringValues, bool $dateAsStringValues, string $dateFormat): array {
        $result = [];

        if ($value instanceof NamedEntityInterface) {
            $result = $value->toArray();
        } elseif ($value instanceof DateTime) {
            $result[$key] = $dateAsStringValues ? $value["value"]->format($dateFormat) : $value["value"];
        } elseif (is_scalar($value)) {
            $result[$key] = $asStringValues ? (string)$value : $value;
        } else {
            $result[$key] = $value;
        }

        return $result;
    }

    public function equals(NamedEntityInterface $other): bool {
        if (get_class($this) !== get_class($other)) {
            return false;
        }

        if ($this instanceof NamedValuesInterface && $other instanceof NamedValuesInterface) {

            if (count($this->values) !== count($other->getValues())) {
                return false;
            }

            $otherValues = $other->getValues();
            foreach ($this->values as $key => $value) {

                if (!isset($otherValues[$key])) {
                    return false;
                }

                $otherValue = $otherValues[$key];

                if ($value instanceof NamedEntityInterface && $otherValue instanceof NamedEntityInterface) {
                    if (!$value->equals($otherValue)) {
                        return false;
                    }
                } else {
                    if ($value !== $otherValue) {
                        return false;
                    }
                }
            }

            return true;
        }
        return false;
    }

    public function getFirstValue(?string $propertyName = null, $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): mixed {
        $result = $this->getValues($propertyName, $searchValue, $comparisonType);
        if (empty($result)) {
            return null;
        }
        return $result[0];
    }

    public function getLastValue(?string $propertyName = null, $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): mixed {
        $result = $this->getValues($propertyName, $searchValue, $comparisonType);
        if (empty($result)) {
            return null;
        }
        return end($result);
    }

    public function count(): int {
        return count($this->values);
    }

    public function toArray(): array {
        return $this->getArray();
    }

    public function toJson(int $flags = JSON_FORCE_OBJECT): string {
        return json_encode($this->toArray(), $flags);
    }

    public static function fromArray(array $data): self {
        $className = get_called_class();
        return new $className($data);
    }

    public static function fromJson(string $data): self {
        return self::fromArray(json_decode($data, true));
    }
}
