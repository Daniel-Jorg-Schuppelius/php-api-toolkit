<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Common;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use Psr\Log\LoggerInterface;

class Person extends NamedEntity {
    protected ?string $salutation;
    protected ?string $firstName;
    protected ?string $middleName;
    protected string $lastName;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function getSalutation(): ?string {
        return $this->salutation ?? null;
    }

    public function getFirstName(): ?string {
        return $this->firstName ?? null;
    }

    public function getMiddleName(): ?string {
        return $this->middleName ?? null;
    }

    public function getLastName(): string {
        return $this->lastName;
    }

    public function setSalutation(?string $salutation): void {
        $this->salutation = $salutation;
    }

    public function setFirstName(?string $firstName): void {
        $this->firstName = $firstName;
    }

    public function setMiddleName(?string $middleName): void {
        $this->middleName = $middleName;
    }

    public function setLastName(string $lastName): void {
        $this->lastName = $lastName;
    }
}