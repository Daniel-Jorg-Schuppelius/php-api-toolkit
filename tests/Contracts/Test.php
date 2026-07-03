<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Test.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Contracts;

use ERRORToolkit\Factories\ConsoleLoggerFactory;
use ERRORToolkit\LoggerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

abstract class Test extends TestCase {
    protected ?LoggerInterface $logger = null;

    public function __construct($name) {
        parent::__construct($name);
        $this->logger = ConsoleLoggerFactory::getLogger();
        // Ohne Registry-Eintrag liefe das ErrorLog-Trait der Clients in den
        // formatarmen syslog-Fallback (kein Caller, zerschriebene CI-Logs)
        LoggerRegistry::setLogger($this->logger);
    }

    protected function setUp(): void {
        $this->logger->info("Setting up test");
    }
}
