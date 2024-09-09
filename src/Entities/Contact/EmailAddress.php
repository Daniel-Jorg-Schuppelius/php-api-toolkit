<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Contact;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class EmailAddress extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        $this->entityName = 'emailAddress';
        parent::__construct($data, $logger);
    }
}
