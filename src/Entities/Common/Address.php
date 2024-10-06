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
        if (!isset($this->countryCode)) {
            $this->countryCode = CountryCode::Germany;
        }
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
        $this->zip = $zip;
    }

    public function setCity(?string $city): void {
        $this->city = $city;
    }

    public function setCountryCode(CountryCode $countryCode): void {
        $this->countryCode = $countryCode;
    }
}
