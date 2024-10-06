<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Bank;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class BIC extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
        $this->entityName = 'bic';
    }

    public function isValid(): bool {
        if (empty($this->value)) {
            return false;
        }

        return preg_match('/^[A-Za-z]{4}[A-Za-z]{2}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/', $this->value) === 1;
    }
}
