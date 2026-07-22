<?php
/*
 * Created on   : Sun Dec 28 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StringValues.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities;

use APIToolkit\Contracts\Abstracts\NamedValues;

/**
 * @extends NamedValues<StringValue>
 */
class StringValues extends NamedValues {
    protected string $entityName = "content";
    protected string $valueClassName = StringValue::class;

    public function toArray(): array {
        $result = [];
        foreach ($this->values as $value) {
            if ($value instanceof StringValue) {
                $result[] = $value->getValue();
            } else {
                $result[] = $value;
            }
        }
        return $result;
    }
}
