<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConsoleLogger.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace APIToolkit\Logger;

use APIToolkit\Contracts\Abstracts\LoggerAbstract;

class ConsoleLogger extends LoggerAbstract {
    public function log($level, string|\Stringable $message, array $context = []): void {
        echo parent::generateLogEntry($level, $message, $context);;
    }
}
