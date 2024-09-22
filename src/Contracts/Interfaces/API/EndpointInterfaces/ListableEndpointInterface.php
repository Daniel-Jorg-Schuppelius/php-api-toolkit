<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\API\EndpointInterfaces;

use APIToolkit\Contracts\Interfaces\API\EndpointInterface;
use APIToolkit\Contracts\Interfaces\NamedEntityInterface;

interface ListableEndpointInterface extends EndpointInterface {
    public function list(array $options = []): NamedEntityInterface;
}
