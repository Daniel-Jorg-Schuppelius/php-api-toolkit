<?php

declare(strict_types=1);

namespace Tests;

use APIToolkit\Entities\Common\Address;
use APIToolkit\Entities\Common\Addresses;
use APIToolkit\Enums\ComparisonType;
use APIToolkit\Enums\CountryCode;
use Tests\Contracts\Test;
use Tests\TestEntities\BoolChecker;
use Tests\TestEntities\DateTimeChecker;
use Tests\TestEntities\FloatChecker;
use Tests\TestEntities\IntChecker;
use Tests\TestEntities\StringChecker;
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
        $data3 = [
            "dateTimeVar1" => "2020-01-01",
            "dateTimeVar2" => "2020-01-01T00:00:00+00:00",
            "dateTimeVar3" => "01.06.2020",
            "dateTimeVar4" => null,
        ];

        $data4 = [
            "stringVar1" => 1,
            "stringVar2" => false,
            "stringVar3" => 1.0,
            "stringVar4" => null,
            "stringVar5" => 1000,
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
        $this->assertEquals(1.5, $floatChecker->getFloatVar5());

        $intChecker = new IntChecker($data2, $this->logger);
        $this->assertEquals(1, $intChecker->getIntVar1());
        $this->assertEquals(1, $intChecker->getIntVar2());
        $this->assertEquals(0, $intChecker->getIntVar3());
        $this->assertEquals(0, $intChecker->getIntVar4());

        $dateChecker = new DateTimeChecker($data3, $this->logger);
        $this->assertEquals("2020-01-01", $dateChecker->getDateTimeVar1()->format("Y-m-d"));
        $this->assertEquals("2020-01-01", $dateChecker->getDateTimeVar2()->format("Y-m-d"));
        $this->assertEquals("2020-06-01", $dateChecker->getDateTimeVar3()->format("Y-m-d"));
        $this->assertNull($dateChecker->getDateTimeVar4());

        $stringChecker = new StringChecker($data4, $this->logger);
        $this->assertEquals("1", $stringChecker->getStringVar1());
        $this->assertEquals("false", $stringChecker->getStringVar2());
        $this->assertEquals("1", $stringChecker->getStringVar3());
        $this->assertEquals("", $stringChecker->getStringVar4());
        $this->assertEquals("1000", $stringChecker->getStringVar5());
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

    public function testToArray() {
        $data = [
            "boolVar1" => true,
            "boolVar2" => "true",
            "boolVar3" => "on",
            "boolVar4" => null,
        ];

        $data1 = [
            "dateTimeVar1" => "2020-01-01",
            "dateTimeVar2" => "2020-01-01T00:00:00+00:00",
            "dateTimeVar3" => "01.06.2020",
            "dateTimeVar4" => null,
        ];


        $boolChecker = new BoolChecker($data, $this->logger);
        $this->assertEquals([
            "boolVar1" => true,
            "boolVar2" => true,
            "boolVar3" => true,
        ], $boolChecker->toArray());

        $dateChecker = new DateTimeChecker($data1, $this->logger);
        $this->assertEquals([
            "dateTimeVar1" => "2020-01-01",
            "dateTimeVar2" => "2020-01-01",
            "dateTimeVar3" => "2020-06-01",
        ], $dateChecker->toArray());
    }

    public function testEquals() {
        $data = [
            "boolVar1" => true,
            "boolVar2" => "true",
            "boolVar3" => "on",
            "boolVar4" => null,
        ];

        $data1 = [
            "boolVar1" => true,
            "boolVar2" => "true",
            "boolVar3" => "on",
            "boolVar4" => null,
        ];
        $data2 = [
            "boolVar1" => true,
            "boolVar2" => true,
            "boolVar3" => true,
            "boolVar4" => null,
        ];
        $data3 = [
            "boolVar1" => false,
            "boolVar2" => true,
            "boolVar3" => true,
            "boolVar4" => null,
        ];

        $boolChecker = new BoolChecker($data, $this->logger);
        $boolChecker1 = new BoolChecker($data1, $this->logger);
        $boolChecker2 = new BoolChecker($data2, $this->logger);
        $boolChecker3 = new BoolChecker($data3, $this->logger);
        $this->assertObjectEquals($boolChecker, $boolChecker1);
        $this->assertObjectEquals($boolChecker1, $boolChecker2);
        $this->assertObjectNotEquals($boolChecker, $boolChecker3);
    }
}
