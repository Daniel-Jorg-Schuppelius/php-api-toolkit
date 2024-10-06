<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArchivableNamedEntityInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\NamedEntityInterfaces;

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;

interface ArchivableNamedEntityInterface extends NamedEntityInterface {
    public function isArchived(): bool;
}
