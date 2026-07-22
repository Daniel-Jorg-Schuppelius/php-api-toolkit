<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Addresses.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Common;

use APIToolkit\Contracts\Abstracts\NamedValues;

/**
 * @extends NamedValues<Address>
 */
class Addresses extends NamedValues {
    protected string $entityName = "content";
    protected string $valueClassName = Address::class;
}
