<?php
/*
 * Created on   : Sat Nov 02 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VAT.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Tax;

use APIToolkit\Contracts\Abstracts\NamedValue;
use CommonToolkit\Helper\Data\VatNumberHelper;
use Psr\Log\LoggerInterface;

class VAT extends NamedValue {
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        if (is_string($data)) {
            $data = VatNumberHelper::normalize($data);
        } elseif (is_null($data)) {
            $data = "";
        }
        parent::__construct($data, $logger);
    }

    public function isValid(): bool {
        return VatNumberHelper::isVatId($this->value);
    }

    public function isValidStrict(): bool {
        return VatNumberHelper::validateVatId($this->value, true);
    }

    public function getCountryCode(): ?string {
        return VatNumberHelper::extractCountryCode($this->value);
    }
}