<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Common;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use APIToolkit\Enums\CountryCode;
use Psr\Log\LoggerInterface;

class Address extends NamedEntity {
    protected ?string $supplement;
    protected ?string $street;
    protected ?string $zip;
    protected ?string $city;
    protected CountryCode $countryCode;

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
        if (!isset($data->countryCode)) {
            $this->countryCode = CountryCode::Germany;
        }
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

    public function getCountryCode(): CountryCode {
        return $this->countryCode;
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

    public function setCountryCode(CountryCode $countryCode): void {
        $this->countryCode = $countryCode;
    }
}
