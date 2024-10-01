<?php

declare(strict_types=1);

namespace APIToolkit\Entities;

use Psr\Log\LoggerInterface;

class ProgramVersion extends Version {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        if (is_null($data)) {
            $data = "v0.0.0";
        }
        parent::__construct($data, $logger);
        $this->entityName = 'version';
    }

    public function isValid(): bool {
        $result = isset($this->value) && is_numeric($this->value) && $this->value >= 0;
        if (is_string($this->value)) {
            $pattern = '/^v?\.?\d+(\.\d+)*[a-z]?$/';
            if (preg_match($pattern, $this->value)) {
                $result = true;
            }
        }

        return $result;
    }
}
