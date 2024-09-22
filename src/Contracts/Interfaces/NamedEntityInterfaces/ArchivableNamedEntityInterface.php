<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\NamedEntityInterfaces;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;

interface ArchivableNamedEntityInterface extends NamedEntityInterface {
    public function isArchived(): bool;
}
