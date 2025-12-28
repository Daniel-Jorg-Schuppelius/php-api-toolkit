<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Address.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Common;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\PostalCodeHelper;
use Psr\Log\LoggerInterface;

class Address extends NamedEntity {
    protected ?string $supplement;
    protected ?string $street;
    protected ?string $zip;
    protected ?string $city;
    protected CountryCode $countryCode;

    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
        if (!isset($this->countryCode)) {
            $this->countryCode = CountryCode::Germany;
        }
        $this->entityName = 'address';
    }

    public function getSupplement(): ?string {
        return $this->supplement ?? null;
    }

    public function getStreet(): ?string {
        return $this->street ?? null;
    }

    public function getZip(): ?string {
        return $this->zip ?? null;
    }

    public function getCity(): ?string {
        return $this->city ?? null;
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
        $this->zip = $zip !== null ? PostalCodeHelper::normalize($zip, $this->countryCode->value) : null;
    }

    public function setCity(?string $city): void {
        $this->city = $city;
    }

    public function setCountryCode(CountryCode $countryCode): void {
        $this->countryCode = $countryCode;
    }

    public function isValid(): bool {
        return $this->isValidZip() && !empty($this->city) && !empty($this->street);
    }

    public function isValidZip(): bool {
        if ($this->zip === null) {
            return false;
        }
        return PostalCodeHelper::isValid($this->zip, $this->countryCode->value);
    }

    public function getFormattedZip(): ?string {
        if ($this->zip === null) {
            return null;
        }
        return PostalCodeHelper::format($this->zip, $this->countryCode->value);
    }

    public function getGermanState(): ?string {
        if ($this->countryCode !== CountryCode::Germany || $this->zip === null) {
            return null;
        }
        return PostalCodeHelper::getGermanState($this->zip);
    }

    public function getFullAddress(): string {
        $parts = [];

        if (!empty($this->supplement)) {
            $parts[] = $this->supplement;
        }

        if (!empty($this->street)) {
            $parts[] = $this->street;
        }

        $zipCity = trim(($this->getFormattedZip() ?? '') . ' ' . ($this->city ?? ''));
        if (!empty($zipCity)) {
            $parts[] = $zipCity;
        }

        if ($this->countryCode !== CountryCode::Germany) {
            $parts[] = $this->countryCode->value;
        }

        return implode(', ', $parts);
    }
}