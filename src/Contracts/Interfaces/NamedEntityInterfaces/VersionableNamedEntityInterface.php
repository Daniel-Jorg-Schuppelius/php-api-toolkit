<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\NamedEntityInterfaces;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use APIToolkit\Entities\Version;

interface VersionableNamedEntityInterface extends NamedEntityInterface {
    public function getVersion(): ?Version;
}
