<?php

declare(strict_types=1);

namespace Tests;

use Tests\Contracts\Test;
use Tests\Entities\Address;
use Tests\Entities\Addresses;

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

        $addresses = new Addresses($data, $this->logger);
        $this->assertInstanceOf(Address::class, $addresses->getValues()[0]);
        $this->assertTrue($addresses->getValues()[0]->isValid());
        $this->assertFalse($addresses->getValues()[1]->isValid());
    }
}