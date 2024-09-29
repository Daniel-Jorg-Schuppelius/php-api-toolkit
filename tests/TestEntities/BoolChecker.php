<?php

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use Psr\Log\LoggerInterface;

class BoolChecker extends NamedEntity {
    protected ?bool $boolVar1;
    protected ?bool $boolVar2;
    protected ?bool $boolVar3;
    protected ?bool $boolVar4;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function getBoolVar1(): bool {
        return $this->boolVar1 ?? false;
    }

    public function getBoolVar2(): bool {
        return $this->boolVar2 ?? false;
    }

    public function getBoolVar3(): bool {
        return $this->boolVar3 ?? false;
    }

    public function getBoolVar4(): bool {
        return $this->boolVar4 ?? false;
    }
}
