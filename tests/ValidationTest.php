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

    public function testGetValidationErrorsReturnsEmptyForValidEntity(): void {
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

    public function testGetValidationErrorsReturnsErrorsForMissingRequiredProperty(): void {
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

    public function testGetValidationErrorsWithNestedEntity(): void {
        // Address has nested validation
        $data = [
            "street" => "Hauptstr. 1",
            "zip" => "12345",
            "city" => "Berlin"
        ];

        $address = new Address($data, $this->logger);
        $errors = $address->getValidationErrors();

        $this->assertIsArray($errors);
        // Address should be valid with these fields
        $this->assertEmpty($errors);
    }

    // ==================== NamedEntity::assertValid ====================

    public function testAssertValidDoesNotThrowForValidEntity(): void {
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

    public function testAssertValidThrowsForInvalidEntity(): void {
        $data = [
            "stringVar1" => "value1",
            // stringVar5 is required but missing
        ];

        $entity = new StringChecker($data, $this->logger);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation failed');

        $entity->assertValid();
    }

    public function testAssertValidExceptionContainsPropertyName(): void {
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

    public function testIsValidReturnsTrueForValidEntity(): void {
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

    public function testIsValidReturnsFalseForInvalidEntity(): void {
        $data = [
            "stringVar1" => "value1",
        ];

        $entity = new StringChecker($data, $this->logger);

        $this->assertFalse($entity->isValid());
    }

    public function testIsValidWithNullableProperties(): void {
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
