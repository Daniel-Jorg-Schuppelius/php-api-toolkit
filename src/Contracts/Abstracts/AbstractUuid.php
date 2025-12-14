<?php
/*
 * Created on   : Thu Jul 10 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractUUID.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts;

use APIToolkit\Entities\ID;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractUuid extends ID {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        if (is_null($data)) {
            $data = $this->generateUuid()->toString();
        } elseif (is_string($data)) {
            $data = strtolower($data);
        }

        parent::__construct($data, $logger);
        $this->entityName = $this->getEntityName();
    }

    public function isValid(): bool {
        if (!isset($this->value) || !is_string($this->value)) {
            return false;
        }

        try {
            Uuid::fromString($this->value);
            return true;
        } catch (InvalidUuidStringException) {
            return false;
        }
    }

    public function __toString(): string {
        return (string) $this->value;
    }

    public static function generate(): static {
        return new static();
    }

    public function getFormatted(): string {
        return (string) $this->value;
    }

    abstract protected function generateUuid(): UuidInterface;
}
