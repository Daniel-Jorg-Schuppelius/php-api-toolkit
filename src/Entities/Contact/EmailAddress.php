<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmailAddress.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

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
