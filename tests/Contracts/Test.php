<?php

declare(strict_types=1);

namespace Tests\Contracts;

use PHPUnit\Framework\TestCase;
use APIToolkit\Logger\ConsoleLoggerFactory;
use Psr\Log\LoggerInterface;

abstract class Test extends TestCase {
    protected ?LoggerInterface $logger = null;

    public function __construct($name) {
        parent::__construct($name);
        $this->logger = ConsoleLoggerFactory::getLogger();
    }

    protected function setUp(): void {
        $this->logger->info("Setting up test");
    }
}