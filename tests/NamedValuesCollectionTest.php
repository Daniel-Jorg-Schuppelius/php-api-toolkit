<?php
/*
 * Created on   : Sat Jan 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedValuesCollectionTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Entities\Common\{Address, Addresses};
use InvalidArgumentException;
use Tests\Contracts\Test;
use Tests\TestEntities\StringCheckers;

/**
 * Tests for the new NamedValues collection methods and validation features.
 */
class NamedValuesCollectionTest extends Test {
    private function createTestAddresses(): Addresses {
        $data = [
            [
                "supplement" => "Hauptsitz",
                "street" => "Hauptstr. 1",
                "zip" => "10001",
                "city" => "Berlin",
            ],
            [
                "supplement" => "Zweigstelle",
                "street" => "Nebenstr. 2",
                "zip" => "20002",
                "city" => "Hamburg",
            ],
            [
                "supplement" => "Lager",
                "street" => "Industriestr. 3",
                "zip" => "30003",
                "city" => "München",
            ],
        ];

        return new Addresses($data, $this->logger);
    }

    // ==================== isEmpty / isNotEmpty ====================

    public function test_is_empty_returns_true_for_empty_collection(): void {
        $addresses = new Addresses([], $this->logger);
        $this->assertTrue($addresses->isEmpty());
        $this->assertFalse($addresses->isNotEmpty());
    }

    public function test_is_empty_returns_false_for_non_empty_collection(): void {
        $addresses = $this->createTestAddresses();
        $this->assertFalse($addresses->isEmpty());
        $this->assertTrue($addresses->isNotEmpty());
    }

    // ==================== filter ====================

    public function test_filter_returns_matching_elements(): void {
        $addresses = $this->createTestAddresses();

        $filtered = $addresses->filter(fn (Address $addr) => str_starts_with($addr->getZip(), '1') || str_starts_with($addr->getZip(), '2'));

        $this->assertCount(2, $filtered);
        $this->assertInstanceOf(Addresses::class, $filtered);
    }

    public function test_filter_returns_empty_when_no_match(): void {
        $addresses = $this->createTestAddresses();

        $filtered = $addresses->filter(fn (Address $addr) => $addr->getCity() === 'Frankfurt');

        $this->assertTrue($filtered->isEmpty());
        $this->assertCount(0, $filtered);
    }

    public function test_filter_preserves_original_collection(): void {
        $addresses = $this->createTestAddresses();
        $originalCount = $addresses->count();

        $addresses->filter(fn (Address $addr) => $addr->getCity() === 'Berlin');

        $this->assertCount($originalCount, $addresses);
    }

    // ==================== map ====================

    public function test_map_transforms_elements(): void {
        $addresses = $this->createTestAddresses();

        $cities = $addresses->map(fn (Address $addr) => $addr->getCity());

        $this->assertIsArray($cities);
        $this->assertCount(3, $cities);
        $this->assertEquals(['Berlin', 'Hamburg', 'München'], $cities);
    }

    public function test_map_with_complex_transformation(): void {
        $addresses = $this->createTestAddresses();

        $formatted = $addresses->map(fn (Address $addr) => sprintf('%s, %s', $addr->getCity(), $addr->getZip()));

        $this->assertEquals(['Berlin, 10001', 'Hamburg, 20002', 'München, 30003'], $formatted);
    }

    // ==================== each ====================

    public function test_each_iterates_over_all_elements(): void {
        $addresses = $this->createTestAddresses();
        $visitedCities = [];

        $addresses->each(function (Address $addr) use (&$visitedCities) {
            $visitedCities[] = $addr->getCity();
        });

        $this->assertCount(3, $visitedCities);
        $this->assertContains('Berlin', $visitedCities);
        $this->assertContains('Hamburg', $visitedCities);
        $this->assertContains('München', $visitedCities);
    }

    public function test_each_provides_index_as_second_parameter(): void {
        $addresses = $this->createTestAddresses();
        $indices = [];

        $addresses->each(function (Address $addr, int $index) use (&$indices) {
            $indices[] = $index;
        });

        $this->assertEquals([0, 1, 2], $indices);
    }

    // ==================== pluck ====================

    public function test_pluck_extracts_property(): void {
        $addresses = $this->createTestAddresses();

        $zips = $addresses->pluck('zip');

        $this->assertEquals(['10001', '20002', '30003'], $zips);
    }

    public function test_pluck_with_non_existent_property(): void {
        $addresses = $this->createTestAddresses();

        $values = $addresses->pluck('nonExistent');

        $this->assertCount(3, $values);
        $this->assertEquals([null, null, null], $values);
    }

    // ==================== find ====================

    public function test_find_returns_first_matching_element(): void {
        $addresses = $this->createTestAddresses();

        $found = $addresses->find(fn (Address $addr) => $addr->getCity() === 'Hamburg');

        $this->assertNotNull($found);
        $this->assertInstanceOf(Address::class, $found);
        $this->assertEquals('Hamburg', $found->getCity());
    }

    public function test_find_returns_null_when_no_match(): void {
        $addresses = $this->createTestAddresses();

        $found = $addresses->find(fn (Address $addr) => $addr->getCity() === 'Frankfurt');

        $this->assertNull($found);
    }

    public function test_find_returns_first_of_multiple_matches(): void {
        $data = [
            ["supplement" => "A", "street" => "Str 1", "zip" => "11111", "city" => "TestCity"],
            ["supplement" => "B", "street" => "Str 2", "zip" => "22222", "city" => "TestCity"],
        ];
        $addresses = new Addresses($data, $this->logger);

        $found = $addresses->find(fn (Address $addr) => $addr->getCity() === 'TestCity');

        $this->assertNotNull($found);
        $this->assertEquals('A', $found->getSupplement());
    }

    // ==================== any ====================

    public function test_any_returns_true_when_one_matches(): void {
        $addresses = $this->createTestAddresses();

        $result = $addresses->any(fn (Address $addr) => $addr->getCity() === 'Berlin');

        $this->assertTrue($result);
    }

    public function test_any_returns_false_when_none_match(): void {
        $addresses = $this->createTestAddresses();

        $result = $addresses->any(fn (Address $addr) => $addr->getCity() === 'Frankfurt');

        $this->assertFalse($result);
    }

    // ==================== all ====================

    public function test_all_returns_true_when_all_match(): void {
        $addresses = $this->createTestAddresses();

        $result = $addresses->all(fn (Address $addr) => strlen($addr->getZip()) === 5);

        $this->assertTrue($result);
    }

    public function test_all_returns_false_when_one_does_not_match(): void {
        $addresses = $this->createTestAddresses();

        $result = $addresses->all(fn (Address $addr) => $addr->getCity() === 'Berlin');

        $this->assertFalse($result);
    }

    public function test_all_returns_true_for_empty_collection(): void {
        $addresses = new Addresses([], $this->logger);

        $result = $addresses->all(fn (Address $addr) => false);

        $this->assertTrue($result); // vacuous truth
    }

    // ==================== Chaining ====================

    public function test_method_chaining(): void {
        $addresses = $this->createTestAddresses();

        $result = $addresses
            ->filter(fn (Address $addr) => strlen($addr->getCity()) > 5)
            ->map(fn (Address $addr) => $addr->getCity());

        $this->assertIsArray($result);
        $this->assertContains('Berlin', $result);
        $this->assertContains('Hamburg', $result);
        $this->assertContains('München', $result);
    }

    // ==================== getValidationErrors ====================

    public function test_get_validation_errors_returns_empty_for_valid_collection(): void {
        $addresses = $this->createTestAddresses();

        $errors = $addresses->getValidationErrors();

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function test_get_validation_errors_returns_indexed_errors(): void {
        // Create a collection with an invalid entity (missing required property)
        $data = [
            ["stringVar1" => "test1", "stringVar5" => "value1"],
            ["stringVar1" => "test2"], // Missing stringVar5 which is not nullable
        ];

        $checkers = new StringCheckers($data, $this->logger);
        $errors = $checkers->getValidationErrors();

        $this->assertIsArray($errors);
        // The second item should have validation errors
        $this->assertNotEmpty($errors);

        // Check that the error key contains the index
        $hasIndexedKey = false;
        foreach (array_keys($errors) as $key) {
            if (str_contains($key, '[1]')) {
                $hasIndexedKey = true;
                break;
            }
        }
        $this->assertTrue($hasIndexedKey, 'Errors should have indexed keys like [1].propertyName');
    }

    // ==================== assertValid ====================

    public function test_assert_valid_does_not_throw_for_valid_collection(): void {
        $addresses = $this->createTestAddresses();

        // Should not throw
        $addresses->assertValid();

        $this->assertTrue(true); // If we reach here, no exception was thrown
    }

    public function test_assert_valid_throws_for_invalid_collection(): void {
        $data = [
            ["stringVar1" => "test1"], // Missing stringVar5 which is not nullable
        ];

        $checkers = new StringCheckers($data, $this->logger);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation failed');

        $checkers->assertValid();
    }

    // ==================== isValid ====================

    public function test_is_valid_returns_true_for_valid_collection(): void {
        $addresses = $this->createTestAddresses();

        $this->assertTrue($addresses->isValid());
    }

    public function test_is_valid_returns_false_for_invalid_collection(): void {
        $data = [
            ["stringVar1" => "test1"], // Missing stringVar5 which is not nullable
        ];

        $checkers = new StringCheckers($data, $this->logger);

        $this->assertFalse($checkers->isValid());
    }
}
