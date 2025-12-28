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
use Psr\Log\LoggerInterface;

class StringValues extends NamedValues {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        $this->entityName = "content";
        $this->valueClassName = StringValue::class;

        parent::__construct($data, $logger);
    }

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