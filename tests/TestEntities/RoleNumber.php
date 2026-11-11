<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoleNumber.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class RoleNumber extends NamedValue {
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        $this->entityName = 'number';
        parent::__construct($data, $logger);
    }
}
