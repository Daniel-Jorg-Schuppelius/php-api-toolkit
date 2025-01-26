<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedEntity.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use ERRORToolkit\Traits\ErrorLog;
use ReflectionClass;
use ReflectionNamedType;
use BackedEnum;
use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Reflection;
use stdClass;
use Throwable;
use UnexpectedValueException;

abstract class NamedEntity implements NamedEntityInterface {
    use ErrorLog;

    protected string $entityName;
    protected string $valueClassName;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        $this->entityName = static::class;
        $this->logger = $logger;

        if (!is_null($data)) {
            $this->setData($data);
        } else {
            $this->initialize();
        }
    }

    public function getEntityName(): string {
        return $this->entityName;
    }

    public function setData($data): NamedEntityInterface {
        if (!is_array($data)) {
            throw new InvalidArgumentException("Data must be an array.");
        }

        $reflectionClass = new ReflectionClass($this);

        foreach ($data as $key => $val) {
            if (is_numeric($key) || !$reflectionClass->hasProperty($key)) {
                $this->logDebug("The property $key does not exist in " . static::class);
                continue;
            }

            $property = $reflectionClass->getProperty($key);
            $type = $property->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $this->handleComplexType($key, $val, $type);
            } elseif ($type instanceof ReflectionNamedType) {
                $this->handleBasicType($key, $val, $type);
            } else {
                $this->{$key} = $val;
            }
        }

        return $this;
    }

    protected function handleBasicType(string $key, $val, ReflectionNamedType $type): void {
        $typeName = $type->getName();

        $filterMap = [
            'bool' => FILTER_VALIDATE_BOOLEAN,
            'float' => FILTER_VALIDATE_FLOAT,
            'int' => FILTER_VALIDATE_INT,
        ];

        if (array_key_exists($typeName, $filterMap) && !is_null($val)) {
            if ($typeName === 'float' && is_string($val)) {
                if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $val)) {
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                } elseif (preg_match('/^\d+,\d+$/', $val)) {
                    $val = str_replace(',', '.', $val);
                }
            }

            $this->{$key} = filter_var($val, $filterMap[$typeName], FILTER_NULL_ON_FAILURE);

            if (is_null($this->{$key})) {
                $this->logError("Invalid $typeName value for $key.");
            }
        } elseif ($typeName === 'string' && !is_string($val) && !is_null($val)) {
            if (is_bool($val)) {
                $this->{$key} = $val ? 'true' : 'false';
            } else {
                $this->{$key} = (string) $val;
            }

            $this->logDebug(
                "The property $key is expected to be a string, but the value of type "
                    . gettype($val) . " was given. Converting to string."
            );
        } else {
            if (is_null($val) && !$type->allowsNull()) {
                throw new UnexpectedValueException("Property $key cannot be null.");
            }

            $this->{$key} = $val; // Fallback für nicht unterstützte Typen oder null
        }
    }

    protected function handleComplexType(string $key, $val, ReflectionNamedType $type): void {
        $className = $type->getName();

        if (is_subclass_of($className, BackedEnum::class)) {
            try {
                $this->{$key} = $className::from($val);
            } catch (Throwable $e) {
                $this->logError("Failed to instantiate $className: " . $e->getMessage());
            }
        } elseif ($key == "content" && !empty($this->valueClassName) && is_subclass_of($className, NamedEntityInterface::class)) {
            $this->{$key} = new $this->valueClassName($val, $this->logger);
        } else {
            try {
                if (is_null($val) && !$type->allowsNull()) {
                    throw new UnexpectedValueException("Property $key cannot be null.");
                } elseif (is_null($val)) {
                    $this->{$key} = null;
                } elseif (is_subclass_of($className, NamedEntityInterface::class)) {
                    $this->{$key} = new $className($val, $this->logger);
                } else {
                    $this->{$key} = new $className($val);
                }
            } catch (Throwable $e) {
                $this->logError("Failed to instantiate $className: " . $e->getMessage());
                throw new UnexpectedValueException("Failed to instantiate $className: " . $e->getMessage());
            }
        }
    }

    protected function initialize(): void {
        foreach ($this->getEntityProperties() as $name => $property) {
            if ($property['type'] instanceof ReflectionNamedType && !$property['type']->isBuiltin()) {

                if (is_subclass_of($property['valueClass'], BackedEnum::class)) {
                    $this->{$name} = $property['valueClass']::from(current($property['valueClass']::cases())->value);
                } elseif (is_subclass_of($property['valueClass'], NamedEntityInterface::class)) {
                    $this->{$name} = new $property['valueClass'](null, $this->logger);
                } elseif ($property['allowsNull']) {
                    $this->{$name} = null;
                } else {
                    try {
                        $this->{$name} = new $property['valueClass']();
                    } catch (Throwable $e) {
                        $this->logError("Failed to instantiate " . $property['valueClass'] . ": " . $e->getMessage());
                    }
                }
            }
        }
    }

    protected function getEntityProperties(bool $noNullValues = false): array {
        $result = [];
        $reflectionClass = new ReflectionClass($this);

        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            $propertyValue = $this->{$propertyName} ?? null;
            // Include property only if it's not inherited from NamedEntity
            if ($property->getDeclaringClass()->getName() !== NamedEntity::class) {
                if ($noNullValues && is_null($propertyValue)) continue;

                $result[$propertyName] = [
                    'class' => $reflectionClass->getName(),
                    'type' => $property->getType(),
                    'value' => $propertyValue,
                    'valueClass' => $property->getType()->getName(),
                    'visibility' => Reflection::getModifierNames($property->getModifiers()),
                    'allowsNull' => $property->getType()->allowsNull(),
                    'isInitialized' => $property->isInitialized($this)
                ];
            }
        }

        return $result;
    }

    protected function getArray(bool $asStringValues = false, bool $dateAsStringValues = true, string $dateFormat = DateTime::RFC3339_EXTENDED): array {
        $result = [];

        foreach ($this->getEntityProperties(true) as $key => $property) {
            $result[$key] = $this->makeArray($key, $property, $asStringValues, $dateAsStringValues, $dateFormat)[$key];
        }

        return $result;
    }

    protected function makeArray($key, $property, bool $asStringValues, bool $dateAsStringValues, string $dateFormat): array {
        $result = [];

        if ($property["value"] instanceof NamedEntityInterface) {
            $valueArray = $property["value"]->toArray();

            if ($property["value"] instanceof NamedValue && isset($valueArray[$key])) {
                $result[$key] =  $valueArray[$key];
            } elseif ($property["value"] instanceof NamedValue && empty($valueArray)) {
                $result[$key] = new stdClass();
            } else {
                $result[$key] = $valueArray;
            }
        } elseif ($property["value"] instanceof BackedEnum) {
            $result[$key] = $asStringValues ? (string)$property["value"]->value : $property["value"]->value;
        } elseif ($property["value"] instanceof DateTime || $property["value"] instanceof \DateTimeImmutable) {
            $result[$key] = $dateAsStringValues ? $property["value"]->format($dateFormat) : $property["value"];
        } else {
            $result[$key] = $asStringValues && is_scalar($property["value"]) ? (string)$property["value"] : $property["value"];
        }

        return $result;
    }

    public function isValid(): bool {
        foreach ($this->getEntityProperties() as $name => $property) {
            if ($property['type'] instanceof ReflectionNamedType && !$property['allowsNull']) {
                if (!$property['isInitialized']) {
                    $this->logWarning("validation -> property {$name} is not initialized", $property);
                    return false;
                } elseif ($property["value"] instanceof NamedEntityInterface && !$property["value"]->isValid()) {
                    $this->logWarning("validation -> property {$name} is not valid", $property);
                    return false;
                }
            }
        }
        return true;
    }

    public function equals(NamedEntityInterface $other): bool {
        if (get_class($this) !== get_class($other)) {
            return false;
        }

        foreach ($this->getEntityProperties() as $key => $property) {
            $thisValue = $property['value'];
            $otherValue = $other->{$key} ?? null;

            if ($thisValue instanceof NamedEntityInterface) {
                if (!$thisValue->equals($otherValue)) {
                    return false;
                }
            } elseif ($thisValue instanceof BackedEnum) {
                if ($thisValue->value !== $otherValue->value) {
                    return false;
                }
            } elseif ($thisValue instanceof DateTime || $thisValue instanceof DateTimeImmutable) {
                if ($thisValue->getTimestamp() !== $otherValue->getTimestamp()) {
                    return false;
                }
            } else {
                if ($thisValue !== $otherValue) {
                    return false;
                }
            }
        }

        return true; // Wenn alle Eigenschaften gleich sind, sind die Objekte gleich
    }

    public function toArray(): array {
        return $this->getArray();
    }

    public function toJson(int $flags = 0): string {
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
