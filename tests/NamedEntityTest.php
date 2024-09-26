<?php

declare(strict_types=1);

namespace Tests;

use APIToolkit\Entities\Common\Address;
use APIToolkit\Entities\Common\Addresses;
use APIToolkit\Enums\ComparisonType;
use APIToolkit\Enums\CountryCode;
use Tests\Contracts\Test;
use Tests\Entities\BoolChecker;
use Tests\Entities\FloatChecker;
use Tests\Entities\IntChecker;
use UnexpectedValueException;

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

    public function testSetData() {
        $data = [
            "boolVar1" => true,
            "boolVar2" => "true",
            "boolVar3" => "on",
            "boolVar4" => null,
        ];

        $data1 = [
            "floatVar1" => 1.0,
            "floatVar2" => "1.0",
            "floatVar3" => "1",
            "floatVar4" => null,
            "floatVar5" => "1,5",
        ];

        $data2 = [
            "intVar1" => 1,
            "intVar2" => "1",
            "intVar3" => "1.0",
            "intVar4" => null,
            "intVar5" => 1000,
        ];

        $boolChecker = new BoolChecker($data, $this->logger);
        $this->assertTrue($boolChecker->getBoolVar1());
        $this->assertTrue($boolChecker->getBoolVar2());
        $this->assertTrue($boolChecker->getBoolVar3());
        $this->assertFalse($boolChecker->getBoolVar4());

        $floatChecker = new FloatChecker($data1, $this->logger);
        $this->assertEquals(1.0, $floatChecker->getFloatVar1());
        $this->assertEquals(1.0, $floatChecker->getFloatVar2());
        $this->assertEquals(1.0, $floatChecker->getFloatVar3());
        $this->assertEquals(0.0, $floatChecker->getFloatVar4());
        $this->assertEquals(1.5, $floatChecker->getFloatVar4());

        $intChecker = new IntChecker($data2, $this->logger);
        $this->assertEquals(1, $intChecker->getIntVar1());
        $this->assertEquals(1, $intChecker->getIntVar2());
        $this->assertEquals(0, $intChecker->getIntVar3());
        $this->assertEquals(0, $intChecker->getIntVar4());
    }

    public function testSetDataException() {
        $data = [
            "intVar1" => 1,
            "intVar2" => "1",
            "intVar3" => "1.0",
            "intVar4" => null,
            "intVar5" => null,
        ];

        $this->expectException(UnexpectedValueException::class);
        new IntChecker($data, $this->logger);
    }
}
