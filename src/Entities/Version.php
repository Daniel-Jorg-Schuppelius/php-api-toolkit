<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Version.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class Version extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        if (is_null($data)) {
            $data = 1;
        }
        parent::__construct($data, $logger);
        $this->entityName = 'version';
    }

    public function isValid(): bool {
        return isset($this->value) && is_numeric($this->value) && $this->value >= 0;
    }
}
