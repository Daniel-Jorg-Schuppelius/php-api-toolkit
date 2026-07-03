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

use APIToolkit\Entities\{ID, UUID};
use Tests\Contracts\Test;

/**
 * Tests for the ID helper methods: fromString(), toString(), equals()
 */
class IDHelperTest extends Test {
    // ==================== ID::fromString ====================

    public function test_from_string_creates_id_with_value(): void {
        $id = ID::fromString('12345', $this->logger);

        $this->assertInstanceOf(ID::class, $id);
        $this->assertEquals('12345', $id->toString());
    }

    public function test_from_string_with_numeric_string(): void {
        $id = ID::fromString('999', $this->logger);

        $this->assertEquals('999', $id->toString());
        $this->assertEquals(999, $id->getValue());
    }

    // ==================== ID::toString ====================

    public function test_to_string_returns_string_representation(): void {
        $id = new ID(12345, $this->logger);

        $this->assertIsString($id->toString());
        $this->assertEquals('12345', $id->toString());
    }

    public function test_to_string_with_zero(): void {
        $id = new ID(0, $this->logger);

        $this->assertEquals('0', $id->toString());
    }

    public function test_to_string_with_null_data(): void {
        $id = new ID(null, $this->logger);

        $this->assertEquals('0', $id->toString());
    }

    // ==================== ID::equals ====================

    public function test_equals_returns_true_for_same_value(): void {
        $id1 = new ID(123, $this->logger);
        $id2 = new ID(123, $this->logger);

        $this->assertTrue($id1->equals($id2));
        $this->assertTrue($id2->equals($id1));
    }

    public function test_equals_returns_false_for_different_value(): void {
        $id1 = new ID(123, $this->logger);
        $id2 = new ID(456, $this->logger);

        $this->assertFalse($id1->equals($id2));
        $this->assertFalse($id2->equals($id1));
    }

    public function test_equals_with_from_string(): void {
        $id1 = ID::fromString('999', $this->logger);
        $id2 = ID::fromString('999', $this->logger);

        $this->assertTrue($id1->equals($id2));
    }

    public function test_equals_with_zeros(): void {
        $id1 = new ID(0, $this->logger);
        $id2 = new ID(0, $this->logger);

        $this->assertTrue($id1->equals($id2));
    }

    // ==================== ID::isValid ====================

    public function test_is_valid_returns_true_for_positive_number(): void {
        $id = new ID(12345, $this->logger);

        $this->assertTrue($id->isValid());
    }

    public function test_is_valid_returns_true_for_zero(): void {
        $id = new ID(0, $this->logger);

        $this->assertTrue($id->isValid());
    }

    public function test_is_valid_returns_false_for_negative_number(): void {
        $id = new ID(-1, $this->logger);

        $this->assertFalse($id->isValid());
    }

    // ==================== UUID Helper Methods ====================

    public function test_uuid_from_string_creates_valid_uuid(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = UUID::fromString($uuidString, $this->logger);

        $this->assertInstanceOf(UUID::class, $uuid);
        $this->assertEquals($uuidString, $uuid->toString());
    }

    public function test_uuid_equals_returns_true_for_same_uuid(): void {
        $uuidStr = '550e8400-e29b-41d4-a716-446655440000';
        $uuid1 = UUID::fromString($uuidStr, $this->logger);
        $uuid2 = UUID::fromString($uuidStr, $this->logger);

        $this->assertTrue($uuid1->equals($uuid2));
    }

    public function test_uuid_equals_returns_false_for_different_uuid(): void {
        $uuid1 = UUID::fromString('550e8400-e29b-41d4-a716-446655440000', $this->logger);
        $uuid2 = UUID::fromString('550e8400-e29b-41d4-a716-446655440001', $this->logger);

        $this->assertFalse($uuid1->equals($uuid2));
    }

    public function test_uuid_is_valid_returns_true_for_valid_uuid(): void {
        $uuid = UUID::fromString('550e8400-e29b-41d4-a716-446655440000', $this->logger);

        $this->assertTrue($uuid->isValid());
    }
}
