<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces;

interface NamedValuesInterface extends NamedEntityInterface {
    public function isReadOnly(): bool;

    public function getValues(): array;
}
