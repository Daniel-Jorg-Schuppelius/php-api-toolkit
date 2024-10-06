<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StringChecker.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use Psr\Log\LoggerInterface;

class StringChecker extends NamedEntity {
    protected ?string $stringVar1;
    protected ?string $stringVar2;
    protected ?string $stringVar3;
    protected ?string $stringVar4;
    protected string $stringVar5;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function getStringVar1(): string {
        return $this->stringVar1 ?? "";
    }

    public function getStringVar2(): string {
        return $this->stringVar2 ?? "";
    }

    public function getStringVar3(): string {
        return $this->stringVar3 ?? "";
    }

    public function getStringVar4(): string {
        return $this->stringVar4 ?? "";
    }

    public function getStringVar5(): string {
        return $this->stringVar5;
    }
}
