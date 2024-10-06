<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedValuesInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces;

interface NamedValuesInterface extends NamedEntityInterface {
    public function isReadOnly(): bool;

    public function getValues(): array;

    public function getFirstValue(): mixed;
    public function getLastValue(): mixed;
}
