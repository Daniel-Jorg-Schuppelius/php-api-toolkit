<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IBAN.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Bank;

use APIToolkit\Contracts\Abstracts\NamedValue;
use CommonToolkit\Helper\Data\BankHelper;
use Psr\Log\LoggerInterface;

class IBAN extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
        $this->entityName = 'iban';
    }

    public function isValid(): bool {
        return BankHelper::checkIBAN($this->value);
    }
}
