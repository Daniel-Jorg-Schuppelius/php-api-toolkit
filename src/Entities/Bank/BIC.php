<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BIC.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Bank;

use APIToolkit\Contracts\Abstracts\NamedValue;
use CommonToolkit\Helper\Data\BankHelper;
use Psr\Log\LoggerInterface;

class BIC extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
        $this->entityName = 'bic';
    }

    public function isValid(): bool {
        return BankHelper::isBIC($this->value);
    }
}
