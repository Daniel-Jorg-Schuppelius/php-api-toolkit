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
use CommonToolkit\Helper\Data\PhoneNumberHelper;
use Psr\Log\LoggerInterface;

class PhoneNumber extends NamedValue {
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
        $this->entityName = 'phoneNumber';
    }

    public function isValid(): bool {
        return PhoneNumberHelper::isPhoneNumber($this->value);
    }

    public function isE164(): bool {
        return PhoneNumberHelper::isE164($this->value);
    }

    public function isGermanNumber(): bool {
        return PhoneNumberHelper::isGermanPhoneNumber($this->value);
    }

    public function isGermanMobile(): bool {
        return PhoneNumberHelper::isGermanMobileNumber($this->value);
    }

    public function toE164(string $defaultCountry = 'DE'): ?string {
        return PhoneNumberHelper::toE164($this->value, $defaultCountry);
    }

    public function format(string $format = 'international', string $defaultCountry = 'DE'): string {
        return PhoneNumberHelper::format($this->value, $format, $defaultCountry);
    }

    public function normalize(): string {
        return PhoneNumberHelper::normalize($this->value);
    }
}