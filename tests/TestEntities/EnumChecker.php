<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnumChecker.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use Psr\Log\LoggerInterface;

class EnumChecker extends NamedEntity {
    protected ?CheckerStatus $nullableStatus;
    protected CheckerStatus $requiredStatus;

    /**
     * @param array<string, mixed>|object|null $data
     */
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function getNullableStatus(): ?CheckerStatus {
        return $this->nullableStatus ?? null;
    }

    public function getRequiredStatus(): ?CheckerStatus {
        return $this->requiredStatus ?? null;
    }
}
