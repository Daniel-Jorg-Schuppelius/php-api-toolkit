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
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        if (is_string($data)) {
            $data = strtoupper(trim($data));
        }
        parent::__construct($data, $logger);
        $this->entityName = 'bic';
    }

    public function isValid(): bool {
        return BankHelper::isBIC($this->value);
    }

    public function getBankInfo(): string|false {
        return BankHelper::checkBIC($this->value);
    }

    public function getBankCode(): ?string {
        if (!$this->isValid()) {
            return null;
        }
        return substr($this->value, 0, 4);
    }

    public function getCountryCode(): ?string {
        if (!$this->isValid()) {
            return null;
        }
        return substr($this->value, 4, 2);
    }

    public function getLocationCode(): ?string {
        if (!$this->isValid()) {
            return null;
        }
        return substr($this->value, 6, 2);
    }

    public function getBranchCode(): ?string {
        if (!$this->isValid() || strlen($this->value) < 11) {
            return null;
        }
        return substr($this->value, 8, 3);
    }

    public function getBIC8(): ?string {
        if (!$this->isValid()) {
            return null;
        }
        return substr($this->value, 0, 8);
    }

    public function getBIC11(): ?string {
        if (!$this->isValid()) {
            return null;
        }
        return strlen($this->value) === 11 ? $this->value : $this->value . 'XXX';
    }
}
