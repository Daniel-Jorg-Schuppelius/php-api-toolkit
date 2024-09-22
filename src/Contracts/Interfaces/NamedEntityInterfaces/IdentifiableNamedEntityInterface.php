<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\NamedEntityInterfaces;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use APIToolkit\Entities\ID;

interface IdentifiableNamedEntityInterface extends NamedEntityInterface {
    public function getID(): ?ID;
}
