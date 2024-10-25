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
    public function __construct($data = null, ?LoggerInterface $logger = null);

    public function getEntityName(): string;
    public function setData($data): self;

    public function isValid(): bool;

    public function equals(NamedEntityInterface $other): bool;

    public function toArray(): array;
    public function toJson(int $flags = 0): string;

    public static function fromArray(array $data, ?LoggerInterface $logger = null): self;
    public static function fromJson(string $data, ?LoggerInterface $logger = null): self;
}
