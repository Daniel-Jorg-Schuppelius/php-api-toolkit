<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HydrationRobustnessTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use Tests\Contracts\Test;
use Tests\TestEntities\{CheckerStatus, EnumChecker, FloatChecker};
use UnexpectedValueException;

class HydrationRobustnessTest extends Test {
    public function test_decimal_point_is_not_mistaken_for_a_thousands_separator(): void {
        // Ohne Komma ist "1.234" in jeder nicht explizit deutschen API ein
        // Dezimalpunkt — die frühere Heuristik machte daraus 1234.0.
        $checker = new FloatChecker(['floatVar1' => '1.234'], $this->logger);

        $this->assertSame(1.234, $checker->getFloatVar1());
    }

    public function test_german_notation_with_comma_is_still_normalized(): void {
        $checker = new FloatChecker([
            'floatVar1' => '1.234,56',
            'floatVar2' => '12,5',
            'floatVar3' => '1234.56',
        ], $this->logger);

        $this->assertSame(1234.56, $checker->getFloatVar1());
        $this->assertSame(12.5, $checker->getFloatVar2());
        $this->assertSame(1234.56, $checker->getFloatVar3());
    }

    public function test_unknown_enum_value_nulls_a_nullable_property(): void {
        $checker = new EnumChecker(['nullableStatus' => 'value-the-api-added-later'], $this->logger);

        $this->assertNull($checker->getNullableStatus());
    }

    public function test_unknown_enum_value_fails_loudly_for_a_required_property(): void {
        // Zuvor blieb die Property uninitialisiert und schlug erst beim
        // Zugriff an ganz anderer Stelle als Fatal auf.
        $this->expectException(UnexpectedValueException::class);

        new EnumChecker(['requiredStatus' => 'value-the-api-added-later'], $this->logger);
    }

    public function test_known_enum_values_still_hydrate(): void {
        $checker = new EnumChecker(['nullableStatus' => 'open', 'requiredStatus' => 'closed'], $this->logger);

        $this->assertSame(CheckerStatus::Open, $checker->getNullableStatus());
        $this->assertSame(CheckerStatus::Closed, $checker->getRequiredStatus());
    }

    public function test_subclass_may_raise_the_minimum_request_interval(): void {
        $client = new class('https://api.example.com') extends ClientAbstract {
            public const MIN_INTERVAL = 0.5;
        };

        // Die Untergrenze der Subklasse gilt (zuvor löste self:: immer die
        // 0.2 der Basisklasse auf, die SDK-Vorgabe war wirkungslos).
        $this->expectException(\InvalidArgumentException::class);
        $client->setRequestInterval(0.3);
    }

    public function test_subclass_minimum_still_allows_disabling_and_higher_values(): void {
        $client = new class('https://api.example.com') extends ClientAbstract {
            public const MIN_INTERVAL = 0.5;
        };

        $client->setRequestInterval(0.0);
        $this->assertSame(0.0, $client->getRequestInterval());

        $client->setRequestInterval(0.65);
        $this->assertSame(0.65, $client->getRequestInterval());
    }
}
