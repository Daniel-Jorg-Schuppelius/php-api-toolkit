<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedEntityInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces;

use Psr\Log\LoggerInterface;

interface NamedEntityInterface {
    /**
     * @param array<string, mixed>|object|null $data
     */
    public function __construct(array|object|null $data = null, ?LoggerInterface $logger = null);

    public function getEntityName(): string;

    /**
     * @param array<string, mixed>|object $data
     */
    public function setData(array|object $data): self;

    public function isValid(): bool;

    /**
     * Get all validation errors for this entity.
     *
     * @return array<string, string> Property name => Error message
     */
    public function getValidationErrors(): array;

    /**
     * Assert that the entity is valid, throwing an exception if not.
     *
     * @throws \InvalidArgumentException
     */
    public function assertValid(): void;

    public function equals(NamedEntityInterface $other): bool;

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array;
    public function toJson(int $flags = 0): string;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?LoggerInterface $logger = null): static;
    public static function fromJson(string $data, ?LoggerInterface $logger = null): static;
}
