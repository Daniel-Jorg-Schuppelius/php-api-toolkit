<?php

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
