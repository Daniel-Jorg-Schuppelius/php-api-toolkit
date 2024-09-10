<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Information;

use APIToolkit\Contracts\Abstracts\NamedValues;
use Psr\Log\LoggerInterface;

class Links extends NamedValues {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        $this->entityName = "content";
        $this->valueClassName = Link::class;

        parent::__construct($data, $logger);
    }
}
