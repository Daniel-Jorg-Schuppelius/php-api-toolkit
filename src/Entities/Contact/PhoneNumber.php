<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Contact;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class PhoneNumber extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    function isValid(): bool {
        $cleanedPhoneNumber = preg_replace('/[\s\-\(\)]+/', '', $this->value);

        if (preg_match('/^\+?[0-9]{7,15}$/', $cleanedPhoneNumber)) {
            return true;
        }

        return false;
    }
}
