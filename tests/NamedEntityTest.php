<?php

declare(strict_types=1);

namespace Tests;

use APIToolkit\Entities\Common\Address;
use APIToolkit\Entities\Common\Addresses;
use APIToolkit\Enums\ComparisonType;
use APIToolkit\Enums\CountryCode;
use PHPUnit\Framework\Constraint\Count;
use Tests\Contracts\Test;

class NamedEntityTest extends Test {
    public function testCreateTestEntity() {
        $data = [
            "content" => [
                [
                    "supplement" => "Rechnungsadressenzusatz",
                    "street" => "Hauptstr. 5",
                    "zip" => "12345",
                    "city" => "Musterort",
                    "countryCode" => "US"
                ],
                [
                    "supplement" => "Rechnungsadressenzusatz1",
                    "street" => "Hauptstr. 52",
                    "zip" => "12344",
                    "city" => "Musterort"
                ],
                [
                    "supplement" => "Rechnungsadressenzusatz2",
                    "street" => "Hauptstr. 52",
                    "zip" => "12444",
                    "city" => "Musterort"
                ]
            ]
        ];

        $data1 = [
            [
                "supplement" => "Rechnungsadressenzusatz",
                "street" => "Hauptstr. 5",
                "zip" => "12345",
                "city" => "Musterort",
                "countryCode" => "US"
            ],
            [
                "supplement" => "Rechnungsadressenzusatz1",
                "street" => "Hauptstr. 52",
                "zip" => "12344",
                "city" => "Musterort"
            ],
            [
                "supplement" => "Rechnungsadressenzusatz2",
                "street" => "Hauptstr. 52",
                "zip" => "12444",
                "city" => "Musterort"
            ]
        ];

        $addresses = new Addresses($data, $this->logger);
        $addresses1 = new Addresses($data1, $this->logger);
        $this->assertInstanceOf(Address::class, $addresses->getValues()[0]);
        $this->assertInstanceOf(Address::class, $addresses->getValues()[1]);
        $this->assertInstanceOf(Address::class, $addresses1->getValues()[0]);
        $this->assertInstanceOf(Address::class, $addresses1->getValues()[1]);
        $this->assertEquals($addresses->getValues(), $addresses1->getValues());
        $this->assertEquals(CountryCode::UnitedStatesOfAmerica, $addresses->getValues()[0]->getCountryCode());
        $this->assertTrue($addresses->getValues()[0]->isValid());
        $this->assertTrue($addresses->getValues()[1]->isValid());
        $this->assertTrue($addresses1->getValues()[0]->isValid());
        $this->assertTrue($addresses1->getValues()[1]->isValid());
        $this->assertEquals($addresses->getValues("zip", "12344")[0]->getSupplement(), "Rechnungsadressenzusatz1");
        $this->assertCount(2, $addresses->getValues("zip", "123", ComparisonType::CONTAINS));
    }
}