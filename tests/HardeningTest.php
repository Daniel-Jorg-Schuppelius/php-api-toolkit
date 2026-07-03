<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HardeningTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace Tests;

use APIToolkit\API\Authentication\{ApiKeyAuthentication, BasicAuthentication, BearerAuthentication};
use InvalidArgumentException;
use JsonException;
use Tests\Contracts\Test;
use Tests\TestEntities\StringCheckers;

class HardeningTest extends Test {
    public function test_literal_zero_credentials_are_valid() {
        // "0" is empty() in PHP — must not be treated as missing credentials.
        $this->assertTrue((new ApiKeyAuthentication('0'))->isValid());
        $this->assertTrue((new BearerAuthentication('0'))->isValid());
        $this->assertTrue((new BasicAuthentication('0', '0'))->isValid());
    }

    public function test_empty_credentials_are_invalid() {
        $this->assertFalse((new ApiKeyAuthentication(''))->isValid());
        $this->assertFalse((new BearerAuthentication(''))->isValid());
        $this->assertFalse((new BasicAuthentication('', 'secret'))->isValid());
    }

    public function test_from_json_throws_on_invalid_json() {
        $this->expectException(JsonException::class);
        StringCheckers::fromJson('{not json');
    }

    public function test_from_json_rejects_non_array_json() {
        $this->expectException(InvalidArgumentException::class);
        StringCheckers::fromJson('"just a string"');
    }

    public function test_from_json_round_trip_still_works() {
        $checkers = StringCheckers::fromJson('{"content": [{"stringVar1": "a"}, {"stringVar1": "b"}]}');

        $this->assertCount(2, $checkers);
    }
}
