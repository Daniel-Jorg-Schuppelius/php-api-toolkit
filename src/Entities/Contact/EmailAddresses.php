<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmailAddresses.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Contact;

use APIToolkit\Contracts\Abstracts\NamedValues;

/**
 * @extends NamedValues<EmailAddress>
 */
class EmailAddresses extends NamedValues {
    protected string $entityName = "content";
    protected string $valueClassName = EmailAddress::class;
}
