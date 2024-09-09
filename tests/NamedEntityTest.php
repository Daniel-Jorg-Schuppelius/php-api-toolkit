<?php

declare(strict_types=1);

namespace Tests;

use APIToolkit\Entities\Common\Address;
use APIToolkit\Entities\Common\Addresses;
use APIToolkit\Enums\ComparisonType;
use Tests\Contracts\Test;

class NamedEntityTest extends Test {
    public function testCreateTestEntity() {
        $data = [
            "content" => [
                [
                    "id" => "123456",
                    "supplement" => "Rechnungsadressenzusatz",
                    "street" => "Hauptstr. 5",
                    "zip" => "12345",
                    "city" => "Musterort",
                ],
                [
                    "supplement" => "Rechnungsadressenzusatz1",
                    "street" => "Hauptstr. 52",
                    "zip" => "12344",
                    "city" => "Musterort",
                    "country" => "DE"
                ]
            ]
        ];

        $data1 = [
            [
                "id" => "123456",
                "supplement" => "Rechnungsadressenzusatz",
                "street" => "Hauptstr. 5",
                "zip" => "12345",
                "city" => "Musterort",
            ],
            [
                "supplement" => "Rechnungsadressenzusatz1",
                "street" => "Hauptstr. 52",
                "zip" => "12344",
                "city" => "Musterort",
                "country" => "DE"
            ]
        ];

        $addresses = new Addresses($data, $this->logger);
        $addresses1 = new Addresses($data1, $this->logger);
        $this->assertInstanceOf(Address::class, $addresses->getValues()[0]);
        $this->assertInstanceOf(Address::class, $addresses->getValues()[1]);
        $this->assertInstanceOf(Address::class, $addresses1->getValues()[0]);
        $this->assertInstanceOf(Address::class, $addresses1->getValues()[1]);
        $this->assertEquals($addresses->getValues(), $addresses1->getValues());
        $this->assertTrue($addresses->getValues()[0]->isValid());
        $this->assertFalse($addresses->getValues()[1]->isValid());
        $this->assertTrue($addresses1->getValues()[0]->isValid());
        $this->assertFalse($addresses1->getValues()[1]->isValid());
        $this->assertEquals($addresses->getValues("id", "123456")[0]->getSupplement(), "Rechnungsadressenzusatz");
        $this->assertEquals($addresses->getValues("id", "123", ComparisonType::CONTAINS)[0]->getSupplement(), "Rechnungsadressenzusatz");
    }
}