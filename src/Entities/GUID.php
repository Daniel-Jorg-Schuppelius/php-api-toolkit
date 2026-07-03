<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GUID.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities;

use APIToolkit\Contracts\Abstracts\AbstractUuid;
use Ramsey\Uuid\{Uuid, UuidInterface};

class GUID extends AbstractUuid {
    public function getEntityName(): string {
        return 'guid';
    }

    protected function generateUuid(): UuidInterface {
        return Uuid::uuid4(); // alternativ: Uuid::uuid1()
    }

    public function getFormatted(): string {
        return '{' . $this->value . '}';
    }
}
