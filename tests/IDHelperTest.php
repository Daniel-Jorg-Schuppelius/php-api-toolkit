<?php
/*
 * Created on   : Sat Jan 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IDHelperTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Entities\ID;
use APIToolkit\Entities\UUID;
use Tests\Contracts\Test;

/**
 * Tests for the ID helper methods: fromString(), toString(), equals()
 */
class IDHelperTest extends Test {

    // ==================== ID::fromString ====================

    public function testFromStringCreatesIdWithValue(): void {
        $id = ID::fromString('12345', $this->logger);

        $this->assertInstanceOf(ID::class, $id);
        $this->assertEquals('12345', $id->toString());
    }

    public function testFromStringWithNumericString(): void {
        $id = ID::fromString('999', $this->logger);

        $this->assertEquals('999', $id->toString());
        $this->assertEquals(999, $id->getValue());
    }

    // ==================== ID::toString ====================

    public function testToStringReturnsStringRepresentation(): void {
        $id = new ID(12345, $this->logger);

        $this->assertIsString($id->toString());
        $this->assertEquals('12345', $id->toString());
    }

    public function testToStringWithZero(): void {
        $id = new ID(0, $this->logger);

        $this->assertEquals('0', $id->toString());
    }

    public function testToStringWithNullData(): void {
        $id = new ID(null, $this->logger);

        $this->assertEquals('0', $id->toString());
    }

    // ==================== ID::equals ====================

    public function testEqualsReturnsTrueForSameValue(): void {
        $id1 = new ID(123, $this->logger);
        $id2 = new ID(123, $this->logger);

        $this->assertTrue($id1->equals($id2));
        $this->assertTrue($id2->equals($id1));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void {
        $id1 = new ID(123, $this->logger);
        $id2 = new ID(456, $this->logger);

        $this->assertFalse($id1->equals($id2));
        $this->assertFalse($id2->equals($id1));
    }

    public function testEqualsWithFromString(): void {
        $id1 = ID::fromString('999', $this->logger);
        $id2 = ID::fromString('999', $this->logger);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsWithZeros(): void {
        $id1 = new ID(0, $this->logger);
        $id2 = new ID(0, $this->logger);

        $this->assertTrue($id1->equals($id2));
    }

    // ==================== ID::isValid ====================

    public function testIsValidReturnsTrueForPositiveNumber(): void {
        $id = new ID(12345, $this->logger);

        $this->assertTrue($id->isValid());
    }

    public function testIsValidReturnsTrueForZero(): void {
        $id = new ID(0, $this->logger);

        $this->assertTrue($id->isValid());
    }

    public function testIsValidReturnsFalseForNegativeNumber(): void {
        $id = new ID(-1, $this->logger);

        $this->assertFalse($id->isValid());
    }

    // ==================== UUID Helper Methods ====================

    public function testUuidFromStringCreatesValidUuid(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = UUID::fromString($uuidString, $this->logger);

        $this->assertInstanceOf(UUID::class, $uuid);
        $this->assertEquals($uuidString, $uuid->toString());
    }

    public function testUuidEqualsReturnsTrueForSameUuid(): void {
        $uuidStr = '550e8400-e29b-41d4-a716-446655440000';
        $uuid1 = UUID::fromString($uuidStr, $this->logger);
        $uuid2 = UUID::fromString($uuidStr, $this->logger);

        $this->assertTrue($uuid1->equals($uuid2));
    }

    public function testUuidEqualsReturnsFalseForDifferentUuid(): void {
        $uuid1 = UUID::fromString('550e8400-e29b-41d4-a716-446655440000', $this->logger);
        $uuid2 = UUID::fromString('550e8400-e29b-41d4-a716-446655440001', $this->logger);

        $this->assertFalse($uuid1->equals($uuid2));
    }

    public function testUuidIsValidReturnsTrueForValidUuid(): void {
        $uuid = UUID::fromString('550e8400-e29b-41d4-a716-446655440000', $this->logger);

        $this->assertTrue($uuid->isValid());
    }
}
