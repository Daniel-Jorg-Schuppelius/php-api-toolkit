<?php
/*
 * Created on   : Sat Jan 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValidationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Entities\Common\Address;
use InvalidArgumentException;
use Tests\Contracts\Test;
use Tests\TestEntities\StringChecker;

/**
 * Tests for the NamedEntity validation methods.
 */
class ValidationTest extends Test {
    // ==================== NamedEntity::getValidationErrors ====================

    public function test_get_validation_errors_returns_empty_for_valid_entity(): void {
        $data = [
            "stringVar1" => "value1",
            "stringVar2" => "value2",
            "stringVar3" => "value3",
            "stringVar4" => "value4",
            "stringVar5" => "value5",
        ];

        $entity = new StringChecker($data, $this->logger);
        $errors = $entity->getValidationErrors();

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function test_get_validation_errors_returns_errors_for_missing_required_property(): void {
        $data = [
            "stringVar1" => "value1",
            // stringVar5 is required (not nullable) but missing
        ];

        $entity = new StringChecker($data, $this->logger);
        $errors = $entity->getValidationErrors();

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('stringVar5', $errors);
    }

    public function test_get_validation_errors_with_nested_entity(): void {
        // Address has nested validation
        $data = [
            "street" => "Hauptstr. 1",
            "zip" => "12345",
            "city" => "Berlin",
        ];

        $address = new Address($data, $this->logger);
        $errors = $address->getValidationErrors();

        $this->assertIsArray($errors);
        // Address should be valid with these fields
        $this->assertEmpty($errors);
    }

    // ==================== NamedEntity::assertValid ====================

    public function test_assert_valid_does_not_throw_for_valid_entity(): void {
        $data = [
            "stringVar1" => "value1",
            "stringVar2" => "value2",
            "stringVar3" => "value3",
            "stringVar4" => "value4",
            "stringVar5" => "value5",
        ];

        $entity = new StringChecker($data, $this->logger);

        // Should not throw
        $entity->assertValid();

        $this->assertTrue(true);
    }

    public function test_assert_valid_throws_for_invalid_entity(): void {
        $data = [
            "stringVar1" => "value1",
            // stringVar5 is required but missing
        ];

        $entity = new StringChecker($data, $this->logger);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation failed');

        $entity->assertValid();
    }

    public function test_assert_valid_exception_contains_property_name(): void {
        $data = [
            "stringVar1" => "value1",
        ];

        $entity = new StringChecker($data, $this->logger);

        try {
            $entity->assertValid();
            $this->fail('Expected InvalidArgumentException to be thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('stringVar5', $e->getMessage());
        }
    }

    // ==================== isValid (improved version) ====================

    public function test_is_valid_returns_true_for_valid_entity(): void {
        $data = [
            "stringVar1" => "value1",
            "stringVar2" => "value2",
            "stringVar3" => "value3",
            "stringVar4" => "value4",
            "stringVar5" => "value5",
        ];

        $entity = new StringChecker($data, $this->logger);

        $this->assertTrue($entity->isValid());
    }

    public function test_is_valid_returns_false_for_invalid_entity(): void {
        $data = [
            "stringVar1" => "value1",
        ];

        $entity = new StringChecker($data, $this->logger);

        $this->assertFalse($entity->isValid());
    }

    public function test_is_valid_with_nullable_properties(): void {
        $data = [
            "stringVar1" => null,  // nullable
            "stringVar2" => null,  // nullable
            "stringVar3" => null,  // nullable
            "stringVar4" => null,  // nullable
            "stringVar5" => "required",  // not nullable
        ];

        $entity = new StringChecker($data, $this->logger);

        $this->assertTrue($entity->isValid());
    }
}
