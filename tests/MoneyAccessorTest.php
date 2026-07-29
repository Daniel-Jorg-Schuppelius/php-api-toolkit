<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MoneyAccessorTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use CommonToolkit\Enums\CurrencyCode;
use Tests\Contracts\Test;
use Tests\TestEntities\MoneyCarrier;

class MoneyAccessorTest extends Test {
    public function test_null_stays_null(): void {
        $entity = $this->entityWithoutCurrency();

        $this->assertNull($entity->total(null));
    }

    public function test_amount_without_currency_field_falls_back_to_euro(): void {
        $money = $this->entityWithoutCurrency()->total(19.99);

        $this->assertNotNull($money);
        $this->assertSame(CurrencyCode::Euro, $money->getCurrency());
        $this->assertSame('19.99', (string) $money->getAmount());
    }

    public function test_currency_enum_property_wins(): void {
        $money = $this->entityWithCurrency(CurrencyCode::SwissFranc)->total(10.0);

        $this->assertNotNull($money);
        $this->assertSame(CurrencyCode::SwissFranc, $money->getCurrency());
    }

    public function test_currency_string_property_is_resolved(): void {
        $money = $this->entityWithCurrency('usd')->total(10.0);

        $this->assertNotNull($money);
        $this->assertSame(CurrencyCode::USDollar, $money->getCurrency());
    }

    public function test_unknown_currency_string_falls_back_to_default(): void {
        $money = $this->entityWithCurrency('XYZ')->total(10.0);

        $this->assertNotNull($money);
        $this->assertSame(CurrencyCode::Euro, $money->getCurrency());
    }

    public function test_explicit_currency_overrides_the_entity_currency(): void {
        $money = $this->entityWithCurrency(CurrencyCode::SwissFranc)->total(10.0, CurrencyCode::USDollar);

        $this->assertNotNull($money);
        $this->assertSame(CurrencyCode::USDollar, $money->getCurrency());
    }

    private function entityWithoutCurrency(): MoneyCarrier {
        return new MoneyCarrier;
    }

    private function entityWithCurrency(CurrencyCode|string $currency): MoneyCarrier {
        return new MoneyCarrier($currency);
    }
}
