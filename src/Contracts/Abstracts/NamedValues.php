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
use ArrayIterator;
use Countable;
use DateTime;
use DateTimeImmutable;
use ERRORToolkit\Traits\ErrorLog;
use InvalidArgumentException;
use IteratorAggregate;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Traversable;

/**
 * @template T of NamedEntityInterface
 * @implements IteratorAggregate<int, T>
 */
abstract class NamedValues implements NamedValuesInterface, Countable, IteratorAggregate {
    use ErrorLog;

    protected string $valueClassName = '';
    protected string $entityName = '';
    protected array $values = [];

    protected bool $readOnly = false;

    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        $this->initializeLogger($logger);

        if (!empty($data) && isset($this->entityName) && $this->entityName == "content" && array_key_exists($this->entityName, $data)) {
            $this->values = $this->validateData($data[$this->entityName]);
        } else {
            $this->values = $this->validateData($data);
        }
    }

    public function getEntityName(): string {
        return $this->entityName;
    }

    /**
     * @return static<T>
     */
    public function getEntities(?string $propertyName = null, mixed $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): NamedValues {
        $className = get_called_class();

        return new $className($this->getValues($propertyName, $searchValue, $comparisonType));
    }

    /**
     * @return array<int, T>
     */
    public function getValues(?string $propertyName = null, mixed $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): array {
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

    /**
     * Get all validation errors for this collection.
     * 
     * @return array<string, string> Property name => Error message
     */
    public function getValidationErrors(): array {
        $errors = [];

        foreach ($this->values as $index => $value) {
            if ($value instanceof NamedEntityInterface && !$value->isValid()) {
                $nestedErrors = $value->getValidationErrors();
                foreach ($nestedErrors as $key => $error) {
                    $errors["[{$index}].{$key}"] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Assert that the collection is valid, throwing an exception if not.
     * 
     * @throws InvalidArgumentException
     */
    public function assertValid(): void {
        $errors = $this->getValidationErrors();
        if (!empty($errors)) {
            $messages = array_map(
                fn($key, $msg) => "{$key}: {$msg}",
                array_keys($errors),
                array_values($errors)
            );
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                "Validation failed: " . implode('; ', $messages)
            );
        }
    }

    public function setData(mixed $data): NamedEntityInterface {
        if ($this->readOnly) {
            self::logErrorAndThrow(
                RuntimeException::class,
                "Cannot modify read-only value."
            );
        }
        $this->values = $this->validateData($data);
        return $this;
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->values);
    }

    protected function searchData(string $propertyName, mixed $searchValue, ComparisonType $comparisonType = ComparisonType::EQUALS): array {
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
                $matches = match ($comparisonType) {
                    ComparisonType::EQUALS => $propertyValue == $searchValue,
                    ComparisonType::CONTAINS => is_string($propertyValue) && str_contains($propertyValue, $searchValue),
                    ComparisonType::GREATER_THAN => is_numeric($propertyValue) && $propertyValue > $searchValue,
                    ComparisonType::LESS_THAN => is_numeric($propertyValue) && $propertyValue < $searchValue,
                    ComparisonType::REGEX => is_string($propertyValue) && preg_match($searchValue, $propertyValue),
                };

                if ($matches) {
                    $result[] = $value;
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
                        $result[] = new $this->valueClassName($item, self::$logger);
                    } else {
                        $result[] = new $this->valueClassName($item);
                    }
                } elseif (is_object($item) && $item instanceof $this->valueClassName) {
                    $result[] = $item;
                } else {
                    $this->logError("Value must be an array of scalars, or a nested array.");
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

    protected function makeArray(string|int $key, mixed $value, bool $asStringValues, bool $dateAsStringValues, string $dateFormat): array {
        $result = [];

        if ($value instanceof NamedEntityInterface) {
            $result = $value->toArray();
        } elseif ($value instanceof DateTime || $value instanceof DateTimeImmutable) {
            $result[$key] = $dateAsStringValues ? $value->format($dateFormat) : $value;
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

    /**
     * @return T|null
     */
    public function getFirstValue(?string $propertyName = null, mixed $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): mixed {
        $result = $this->getValues($propertyName, $searchValue, $comparisonType);
        if (empty($result)) {
            return null;
        }
        return $result[0];
    }

    /**
     * @return T|null
     */
    public function getLastValue(?string $propertyName = null, mixed $searchValue = null, ComparisonType $comparisonType = ComparisonType::EQUALS): mixed {
        $result = $this->getValues($propertyName, $searchValue, $comparisonType);
        if (empty($result)) {
            return null;
        }
        return end($result);
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool {
        return empty($this->values);
    }

    /**
     * Check if the collection is not empty.
     */
    public function isNotEmpty(): bool {
        return !$this->isEmpty();
    }

    /**
     * Filter the collection using a callback.
     * 
     * @param callable(T, int): bool $callback
     * @return static<T>
     */
    public function filter(callable $callback): static {
        $result = new static(null, self::$logger);
        $result->values = array_values(array_filter($this->values, $callback));
        return $result;
    }

    /**
     * Apply a callback to each element and return the results.
     * 
     * @template TReturn
     * @param callable(T, int): TReturn $callback
     * @return array<int, TReturn>
     */
    public function map(callable $callback): array {
        return array_map($callback, $this->values);
    }

    /**
     * Execute a callback for each element.
     * 
     * @param callable(T, int): void $callback
     */
    public function each(callable $callback): void {
        foreach ($this->values as $key => $value) {
            $callback($value, $key);
        }
    }

    /**
     * Extract a single property from each element.
     * 
     * @return array<int, mixed>
     */
    public function pluck(string $property): array {
        return $this->map(function ($item) use ($property) {
            if ($item instanceof NamedEntityInterface) {
                $getter = 'get' . ucfirst($property);
                if (method_exists($item, $getter)) {
                    return $item->$getter();
                }
            }
            return $item->{$property} ?? null;
        });
    }

    /**
     * Find the first element matching a callback.
     * 
     * @param callable(T, int): bool $callback
     * @return T|null
     */
    public function find(callable $callback): mixed {
        foreach ($this->values as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Check if any element matches a callback.
     * 
     * @param callable(T, int): bool $callback
     */
    public function any(callable $callback): bool {
        return $this->find($callback) !== null;
    }

    /**
     * Check if all elements match a callback.
     * 
     * @param callable(T, int): bool $callback
     */
    public function all(callable $callback): bool {
        foreach ($this->values as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }
        return true;
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

    public static function fromArray(array $data, ?LoggerInterface $logger = null): self {
        $className = get_called_class();
        return new $className($data, $logger);
    }

    public static function fromJson(string $data, ?LoggerInterface $logger = null): self {
        return self::fromArray(json_decode($data, true), $logger);
    }
}
