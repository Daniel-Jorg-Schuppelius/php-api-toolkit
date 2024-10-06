<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchableEndpointInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\API\EndpointInterfaces;

use APIToolkit\Contracts\Interfaces\API\EndpointInterface;
use APIToolkit\Contracts\Interfaces\NamedEntityInterface;

interface SearchableEndpointInterface extends EndpointInterface {
    public function search(array $queryParams = [], array $options = []): ?NamedEntityInterface;
}
