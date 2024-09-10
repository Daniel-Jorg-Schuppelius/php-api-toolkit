<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Information;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class Link extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    function isValid(bool $onlineCheck = false): bool {
        if (!filter_var($this->value, FILTER_VALIDATE_URL)) {
            return false;
        } elseif (!preg_match("/^http(s)?:\\/\\//", $this->value)) {
            $this->value = "http://$this->value";
        }

        $headers = @get_headers($this->value);

        if ($onlineCheck && $headers && strpos($headers[0], '200')) {
            return true;  // Link ist gültig, Statuscode 200
        } elseif (!$onlineCheck) {
            return true;  // Link ist gültig, kein Online-Check
        }

        return false;
    }
}
