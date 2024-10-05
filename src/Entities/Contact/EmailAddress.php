<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Contact;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class EmailAddress extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function isValid(bool $onlineCheck = false): bool {
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = substr(strrchr($this->value, "@"), 1);

        if ($onlineCheck && !checkdnsrr($domain, "MX")) {
            return false;  // Ungültige Domain oder keine MX-Records
        }

        return true;
    }
}
