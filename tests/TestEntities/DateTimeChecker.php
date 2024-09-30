<?php

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use DateTime;
use PhpParser\Node\Stmt\Finally_;
use Psr\Log\LoggerInterface;

class DateTimeChecker extends NamedEntity {
    protected ?DateTime $dateTimeVar1;
    protected ?DateTime $dateTimeVar2;
    protected ?DateTime $dateTimeVar3;
    protected ?DateTime $dateTimeVar4;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function getDateTimeVar1(): ?DateTime {
        return $this->dateTimeVar1 ?? null;
    }

    public function getDateTimeVar2(): ?DateTime {
        return $this->dateTimeVar2 ?? null;
    }

    public function getDateTimeVar3(): ?DateTime {
        return $this->dateTimeVar3 ?? null;
    }

    public function getDateTimeVar4(): ?DateTime {
        return $this->dateTimeVar4 ?? null;
    }

    protected function getArray(bool $asStringValues = true, bool $dateAsStringValues = true, string $dateFormat = "Y-m-d"): array {
        return parent::getArray($asStringValues, $dateAsStringValues, $dateFormat);
    }
}
