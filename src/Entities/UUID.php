<?php
/*
 * Created on   : Thu Jul 10 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UUID.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities;

use APIToolkit\Contracts\Abstracts\AbstractUuid;
use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid as BaseUuid;

class UUID extends AbstractUuid {
    public function getEntityName(): string {
        return 'uuid';
    }

    protected function generateUuid(): UuidInterface {
        return BaseUuid::uuid4();
    }
}
