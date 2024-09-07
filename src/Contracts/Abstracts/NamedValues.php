<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use APIToolkit\Contracts\Interfaces\NamedValueInterface;
use APIToolkit\Contracts\Interfaces\NamedValuesInterface;
use APIToolkit\Enums\ComparisonType;
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

    public function getValues(?string $propertyName = null, $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): array {
        if (is_null($propertyName)) {
            return $this->values;
        } else {
            return $this->searchData($propertyName, $searchValue, $comparisonType);
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
                        throw new \InvalidArgumentException("Unsupported comparison type: $comparisonType");
                }
            }
        }
        return $result;
    }

    protected function validateData($data) {
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

    protected function isArrayFullyNumeric($array) {
        $keys = array_keys($array);

        $nonNumericKeys = array_filter($keys, function ($key) {
            return !is_int($key);
        });

        return count($nonNumericKeys) === 0;
    }

    public function count(): int {
        return count($this->values);
    }

    public function toArray(): array {
        $result = [];
        foreach ($this->values as $key => $value) {
            if ($value instanceof NamedValueInterface && $value->getEntityName() == $this->valueClassName) {
                $result[] = $value->getValue();
            } elseif ($value instanceof NamedEntityInterface) {
                $result[] = $value->toArray();
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
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
