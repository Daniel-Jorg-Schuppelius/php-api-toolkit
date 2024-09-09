<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\API;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use Tests\Entities\ID;

interface EndpointInterface {
    public function get(?ID $id = null): NamedEntityInterface;
}
