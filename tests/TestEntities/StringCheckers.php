<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StringChecker.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedValues;
use Psr\Log\LoggerInterface;

class StringCheckers extends NamedValues {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        $this->entityName = "content";
        $this->valueClassName = StringChecker::class;

        parent::__construct($data, $logger);
    }
}
