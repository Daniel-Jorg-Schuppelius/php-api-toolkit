<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces;

interface NamedValueInterface extends NamedEntityInterface {
    public function isReadOnly(): bool;

    public function getValue();
}
