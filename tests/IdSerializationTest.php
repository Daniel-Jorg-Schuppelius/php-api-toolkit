<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdSerializationTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use Tests\Contracts\Test;
use Tests\TestEntities\{FloatChecker, IdCarrier};

class IdSerializationTest extends Test {
    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    public function test_ids_are_emitted_flat_whatever_their_entity_name(): void {
        $carrier = IdCarrier::fromJson(json_encode([
            'id' => 1,
            'other_id' => 42,
            'owner_id' => strtoupper(self::UUID),
        ]) ?: '');

        $data = $carrier->toArray();

        $this->assertSame(1, $data['id']);
        $this->assertSame(42, $data['other_id']);
        $this->assertSame(self::UUID, $data['owner_id']);
    }

    public function test_flat_ids_survive_a_round_trip(): void {
        $json = IdCarrier::fromJson('{"other_id":42,"owner_id":"' . self::UUID . '"}')->toJson();

        $this->assertSame(['other_id' => 42, 'owner_id' => self::UUID], json_decode(IdCarrier::fromJson($json)->toJson(), true));
    }

    public function test_wrapped_id_keeps_its_object_and_round_trips(): void {
        $carrier = IdCarrier::fromJson('{"created_id":{"id":7}}');

        $this->assertSame(['id' => 7], $carrier->toArray()['created_id']);
        $this->assertSame(['id' => 7], IdCarrier::fromJson($carrier->toJson())->toArray()['created_id']);
    }

    public function test_other_named_values_keep_their_object(): void {
        $carrier = IdCarrier::fromJson('{"role":{"number":10308}}');

        $this->assertSame(['number' => 10308], $carrier->toArray()['role']);
    }

    public function test_float_keeps_all_digits(): void {
        $checker = FloatChecker::fromJson('{"floatVar5":123456789012.1234}');

        $this->assertSame(123456789012.1234, $checker->getFloatVar5());
    }
}
