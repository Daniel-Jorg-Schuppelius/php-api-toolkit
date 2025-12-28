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
use CommonToolkit\Helper\Data\EmailHelper;
use Psr\Log\LoggerInterface;

class EmailAddress extends NamedValue {
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        if (is_string($data)) {
            $data = EmailHelper::normalize($data);
        }
        parent::__construct($data, $logger);
        $this->entityName = 'emailAddress';
    }

    public function isValid(): bool {
        return EmailHelper::isEmail($this->value);
    }

    public function isValidStrict(bool $checkDns = false): bool {
        return EmailHelper::validateEmail($this->value, $checkDns);
    }

    public function getDomain(): ?string {
        return EmailHelper::extractDomain($this->value);
    }

    public function getLocalPart(): ?string {
        return EmailHelper::extractLocalPart($this->value);
    }

    public function isDisposable(): bool {
        return EmailHelper::isDisposableEmail($this->value);
    }

    public function isFreeProvider(): bool {
        return EmailHelper::isFreeEmailProvider($this->value);
    }
}