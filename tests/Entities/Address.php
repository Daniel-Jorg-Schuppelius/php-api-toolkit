<?php

declare(strict_types=1);

namespace Tests\Entities;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use Psr\Log\LoggerInterface;

class Address extends NamedEntity {
    protected ID $id;
    protected ?string $supplement;
    protected ?string $street;
    protected ?string $zip;
    protected ?string $city;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }

    public function getID(): ID {
        return $this->id;
    }

    public function getSupplement(): ?string {
        return $this->supplement;
    }

    public function getStreet(): ?string {
        return $this->street;
    }

    public function getZip(): ?string {
        return $this->zip;
    }

    public function getCity(): ?string {
        return $this->city;
    }

    public function setSupplement(?string $supplement): void {
        $this->supplement = $supplement;
    }

    public function setStreet(?string $street): void {
        $this->street = $street;
    }

    public function setZip(?string $zip): void {
        $this->zip = $zip;
    }

    public function setCity(?string $city): void {
        $this->city = $city;
    }
}