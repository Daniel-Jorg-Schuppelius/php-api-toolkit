<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimestampableNamedEntityInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\NamedEntityInterfaces;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use DateTime;

interface TimestampableNamedEntityInterface extends NamedEntityInterface {
    public function getCreatedDate(): ?DateTime;
}
