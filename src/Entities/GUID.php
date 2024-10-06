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

use Psr\Log\LoggerInterface;

class GUID extends ID {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        if (isset($data) && is_string($data)) {
            $data = strtolower($data);
        } elseif (is_null($data)) {
            $data = "00000000-0000-0000-0000-000000000000";
        }
        parent::__construct($data, $logger);
        $this->entityName = 'guid';
    }

    public function isValid(): bool {
        $regex = '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/';

        return isset($this->value) && preg_match($regex, $this->value) === 1;
    }
}
