<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\NamedEntityInterfaces;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use DateTime;

interface TimestampableNamedEntityInterface extends NamedEntityInterface {
    public function getCreatedDate(): ?DateTime;
}
