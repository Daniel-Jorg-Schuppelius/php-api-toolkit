<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Persons.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Common;

use APIToolkit\Contracts\Abstracts\NamedValues;

/**
 * @extends NamedValues<Person>
 */
class Persons extends NamedValues {
    protected string $entityName = "content";
    protected string $valueClassName = Person::class;
}
