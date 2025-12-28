<?php
/*
 * Created on   : Sun Dec 28 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StringValue.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts;

use Psr\Log\LoggerInterface;

abstract class StringValue extends NamedValue {
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }
}