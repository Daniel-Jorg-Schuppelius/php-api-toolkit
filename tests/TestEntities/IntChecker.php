<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntChecker.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use Psr\Log\LoggerInterface;

class IntChecker extends NamedEntity {
    protected ?int $intVar1;
    protected ?int $intVar2;
    protected ?int $intVar3;
    protected ?int $intVar4;
    protected int $intVar5;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function getIntVar1(): int {
        return $this->intVar1 ?? 0;
    }

    public function getIntVar2(): int {
        return $this->intVar2 ?? 0;
    }

    public function getIntVar3(): int {
        return $this->intVar3 ?? 0;
    }

    public function getIntVar4(): int {
        return $this->intVar4 ?? 0;
    }

    public function getIntVar5(): int {
        return $this->intVar5;
    }
}
