<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces;

use Psr\Log\LoggerInterface;

interface LoggerFactoryInterface {
    public static function getLogger(): LoggerInterface;
}
