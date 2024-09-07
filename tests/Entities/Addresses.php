<?php

declare(strict_types=1);

namespace Tests\Entities;

use APIToolkit\Contracts\Abstracts\NamedValues;
use Psr\Log\LoggerInterface;

class Addresses extends NamedValues {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        $this->entityName = "content";
        $this->valueClassName = Address::class;

        parent::__construct($data, $logger);
    }
}