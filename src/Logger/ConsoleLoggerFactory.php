<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConsoleLoggerFactory.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Logger;

use APIToolkit\Contracts\Interfaces\LoggerFactoryInterface;
use Psr\Log\LoggerInterface;

class ConsoleLoggerFactory implements LoggerFactoryInterface {
    private static ?LoggerInterface $logger = null;

    public static function getLogger(): LoggerInterface {
        if (self::$logger === null) {
            self::$logger = new ConsoleLogger();
        }
        return self::$logger;
    }
}
