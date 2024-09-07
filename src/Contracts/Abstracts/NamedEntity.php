<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use ReflectionClass;
use ReflectionNamedType;
use BackedEnum;
use DateTime;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Reflection;
use stdClass;
use UnexpectedValueException;

abstract class NamedEntity implements NamedEntityInterface {
    protected ?LoggerInterface $logger;

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
        if (is_array($data)) {
            $reflectionClass = new ReflectionClass($this);
            foreach ($data as $key => $val) {
                if (is_numeric($key) || !$reflectionClass->hasProperty($key)) {
                    if ($this->logger) {
                        $this->logger->warning("The property $key does not exist in " . static::class);
                    } else {
                        error_log("Warning: The property $key does not exist in " . static::class);
                    }
                    continue;
                }

                $property = $reflectionClass->getProperty($key);
                $type = $property->getType();

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $className = $type->getName();

                    if (is_subclass_of($className, BackedEnum::class)) {
                        try {
                            $this->{$key} = $className::from($val);
                        } catch (\Throwable $e) {
                            if ($this->logger) {
                                $this->logger->error("Failed to instantiate $className: " . $e->getMessage() . ". If this data is coming from the lexoffice API, update the Enum.");
                            } else {
                                error_log("Failed to instantiate $className: " . $e->getMessage() . ". If this data is coming from the lexoffice API, update the Enum.");
                            }
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
                        } catch (\Throwable $e) {
                            throw new UnexpectedValueException("Failed to instantiate $className: " . $e->getMessage());
                        }
                    }
                } else {
                    $this->{$key} = $val;
                }
            }
        } else {
            throw new InvalidArgumentException("Data must be an array.");
        }

        return $this;
    }

    protected function initialize() {
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
                    } catch (\Throwable $e) {
                        if ($this->logger) {
                            $this->logger->error("Failed to instantiate " . $property['valueClass'] . ": " . $e->getMessage());
                        } else {
                            error_log("Failed to instantiate " . $property['valueClass'] . ": " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    protected function getEntityProperties(bool $noNullValues = false) {
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

    protected function getArray(string $dateFormat = DateTime::RFC3339_EXTENDED): array {
        $result = [];

        foreach ($this->getEntityProperties(true) as $key => $property) {
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
                $result[$key] = $property["value"]->value;
            } elseif ($property["value"] instanceof DateTime) {
                $result[$key] = $property["value"]->format($dateFormat);
            } else {
                $result[$key] = $property["value"];
            }
        }

        return $result;
    }

    public function isValid(): bool {
        foreach ($this->getEntityProperties() as $name => $property) {
            if ($property['type'] instanceof ReflectionNamedType && !$property['allowsNull']) {
                if (!$property['isInitialized']) {
                    if ($this->logger) {
                        $this->logger->warning("validation -> property {$name} is not initialized", $property);
                    }
                    return false;
                } elseif ($property["value"] instanceof NamedEntityInterface && !$property["value"]->isValid()) {
                    if ($this->logger) {
                        $this->logger->warning("validation -> property {$name} is not valid", $property);
                    }
                    return false;
                }
            }
        }
        return true;
    }

    public function toArray(): array {
        return $this->getArray();
    }

    public function toJson(int $flags = 0): string {
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