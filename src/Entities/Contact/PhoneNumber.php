<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneNumber.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Contact;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class PhoneNumber extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function isValid(): bool {
        $cleanedPhoneNumber = preg_replace('/[\s\-\(\)]+/', '', $this->value);

        if (preg_match('/^\+?[0-9]{7,15}$/', $cleanedPhoneNumber)) {
            return true;
        }

        return false;
    }
}
