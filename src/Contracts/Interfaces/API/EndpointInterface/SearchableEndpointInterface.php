<?php

declare(strict_types=1);

namespace Lexoffice\Contracts\Interfaces\API;

use APIToolkit\Contracts\Interfaces\API\EndpointInterface;
use APIToolkit\Contracts\Interfaces\NamedEntityInterface;

interface SearchableEndpointInterface extends EndpointInterface {
    public function search(array $queryParams = [], array $options = []): NamedEntityInterface;
}
