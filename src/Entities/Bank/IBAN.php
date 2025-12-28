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
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        if (is_string($data)) {
            $data = strtoupper(str_replace(' ', '', trim($data)));
        }
        parent::__construct($data, $logger);
        $this->entityName = 'iban';
    }

    public function isValid(): bool {
        return BankHelper::isIBAN($this->value);
    }

    public function isValidStrict(): bool {
        return BankHelper::checkIBAN($this->value);
    }

    public function isAnonymized(): bool {
        return BankHelper::isIBANAnon($this->value);
    }

    public function getCountryCode(): ?string {
        if (!$this->isValid()) {
            return null;
        }
        return substr($this->value, 0, 2);
    }

    public function getCheckDigits(): ?string {
        if (!$this->isValid()) {
            return null;
        }
        return substr($this->value, 2, 2);
    }

    public function getBLZ(): ?string {
        $parts = BankHelper::splitIBAN($this->value);
        return $parts !== false ? $parts['BLZ'] : null;
    }

    public function getAccountNumber(): ?string {
        $parts = BankHelper::splitIBAN($this->value);
        return $parts !== false ? $parts['KTO'] : null;
    }

    public function getBIC(): ?string {
        $bic = BankHelper::bicFromIBAN($this->value);
        return $bic !== '' ? $bic : null;
    }

    public function getFormatted(): string {
        if (!$this->isValid()) {
            return $this->value ?? '';
        }
        return trim(chunk_split($this->value, 4, ' '));
    }

    public static function fromGermanBankAccount(string $blz, string $kto, ?LoggerInterface $logger = null): self {
        $iban = BankHelper::generateGermanIBAN($blz, $kto);
        return new self($iban, $logger);
    }
}