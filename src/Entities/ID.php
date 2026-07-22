<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ID.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class ID extends NamedValue {
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        // Nur setzen, wenn eine abgeleitete Klasse (z. B. UUID/GUID über
        // AbstractUuid) den Namen nicht bereits vor diesem Konstruktor
        // festgelegt hat.
        if ($this->entityName === '') {
            $this->entityName = 'id';
        }
        if (is_null($data)) {
            $data = 0;
        }
        parent::__construct($data, $logger);
    }

    public function isValid(): bool {
        return isset($this->value) && is_numeric($this->value) && $this->value >= 0;
    }
}
