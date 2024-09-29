<?php

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use Psr\Log\LoggerInterface;

class FloatChecker extends NamedEntity {
    protected ?float $floatVar1;
    protected ?float $floatVar2;
    protected ?float $floatVar3;
    protected ?float $floatVar4;
    protected float $floatVar5;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function getFloatVar1(): float {
        return $this->floatVar1 ?? 0.0;
    }

    public function getFloatVar2(): float {
        return $this->floatVar2 ?? 0.0;
    }

    public function getFloatVar3(): float {
        return $this->floatVar3 ?? 0.0;
    }

    public function getFloatVar4(): float {
        return $this->floatVar4 ?? 0.0;
    }

    public function getFloatVar5(): float {
        return $this->floatVar5 ?? 0.0;
    }
}
