<?php
/*
 * Created on   : Sat Dec 28 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UUIDTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Entities;

use APIToolkit\Entities\{GUID, UUID};
use Tests\Contracts\Test;

class UUIDTest extends Test {
    public function test_create_uuid_entity(): void {
        $uuid = new UUID(null, $this->logger);
        $this->assertTrue($uuid->isValid());
        $this->assertNotEmpty($uuid->getValue());
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/',
            $uuid->getValue()
        );
    }

    public function test_uuid_from_string(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = new UUID($uuidString, $this->logger);
        $this->assertTrue($uuid->isValid());
        $this->assertEquals($uuidString, $uuid->getValue());
    }

    public function test_uuid_uppercase_normalization(): void {
        $uuidString = '550E8400-E29B-41D4-A716-446655440000';
        $uuid = new UUID($uuidString, $this->logger);
        $this->assertEquals(strtolower($uuidString), $uuid->getValue());
    }

    public function test_invalid_uuid(): void {
        $uuid = new UUID('not-a-valid-uuid', $this->logger);
        $this->assertFalse($uuid->isValid());
    }

    public function test_uuid_generate(): void {
        $uuid1 = UUID::generate();
        $uuid2 = UUID::generate();
        $this->assertTrue($uuid1->isValid());
        $this->assertTrue($uuid2->isValid());
        $this->assertNotEquals($uuid1->getValue(), $uuid2->getValue());
    }

    public function test_uuid_to_string(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = new UUID($uuidString, $this->logger);
        $this->assertEquals($uuidString, (string) $uuid);
    }

    public function test_guid_get_formatted(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $guid = new GUID($uuidString, $this->logger);
        $this->assertEquals('{' . $uuidString . '}', $guid->getFormatted());
    }

    public function test_uuid_get_formatted(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = new UUID($uuidString, $this->logger);
        $this->assertEquals($uuidString, $uuid->getFormatted());
    }

    public function test_uuid_entity_name(): void {
        $uuid = new UUID(null, $this->logger);
        $this->assertEquals('uuid', $uuid->getEntityName());
    }

    public function test_guid_entity_name(): void {
        $guid = new GUID(null, $this->logger);
        $this->assertEquals('guid', $guid->getEntityName());
    }
}
